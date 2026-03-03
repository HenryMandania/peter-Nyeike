<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\Widgets;

use App\Models\Shift;
use App\Models\Purchase;
use App\Models\Expense;
use App\Services\BalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ShiftStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $service = new BalanceService();
        
        // 1. Fetch Summary Data
        $purchaseStats = Purchase::query()
            ->selectRaw('SUM(total_amount) as total_val, SUM(transaction_fee) as fees, COUNT(*) as qty')
            ->first();
            
        $totalExpenses = Expense::sum('amount');
        $activeShifts = Shift::where('status', 'open')->count();
        
        // 2. Calculate Complex Running Balance (using your existing service)
        $totalRunningBalance = Shift::all()->reduce(fn ($carry, $shift) => $carry + $service->calculate($shift), 0);

        // 3. Generate Real Intelligence Trends (Last 7 Days)
        $trends = $this->getTrends();

        return [
            Stat::make('Active Shifts', $activeShifts)
                ->description($activeShifts > 0 ? 'Field operations ongoing' : 'All shifts closed')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($activeShifts > 0 ? 'success' : 'gray')
                ->icon('heroicon-m-clock'),

            Stat::make('Total Purchases', 'KES ' . number_format($purchaseStats->total_val ?? 0, 0))
                ->description('Gross inventory investment')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($trends['purchases'])
                ->color('info'),

            Stat::make('Total Expenses', 'KES ' . number_format($totalExpenses, 0))
                ->description('Operational overhead')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart($trends['expenses'])
                ->color('danger'),

            Stat::make('Purchase Volume', number_format($purchaseStats->qty ?? 0) . ' txns')
                ->description('Total procurement count')
                ->descriptionIcon('heroicon-m-hashtag')
                ->color('warning'),

            Stat::make('Total Fees', 'KES ' . number_format($purchaseStats->fees ?? 0, 0))
                ->description('Transaction cost leakage')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('gray'),

            Stat::make('Network Liquidity', 'KES ' . number_format($totalRunningBalance, 0))
                ->description('Total cash in circulation')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($trends['purchases']) // Using purchase trend as a proxy for activity
                ->color('primary'),
        ];
    }

    /**
     * Pushes real-time data trends to the sparkline charts
     */
    protected function getTrends(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $purchases = Purchase::query()
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('total', 'date');

        $expenses = Expense::query()
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('total', 'date');

        return [
            'purchases' => $days->map(fn ($date) => $purchases->get($date, 0))->toArray(),
            'expenses'  => $days->map(fn ($date) => $expenses->get($date, 0))->toArray(),
        ];
    }
}