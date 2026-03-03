<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\Widgets;

use App\Models\Shift;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\FloatRequest; // Added
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
        
        $purchaseStats = Purchase::query()
            ->selectRaw('SUM(total_amount) as total_val, SUM(transaction_fee) as fees, COUNT(*) as qty')
            ->first();
            
        $totalExpenses = Expense::sum('amount');
        $activeShifts = Shift::where('status', 'open')->count();
        
        // Total Liquidity across all shifts
        $totalRunningBalance = Shift::all()->reduce(fn ($carry, $shift) => $carry + $service->calculate($shift), 0);

        $trends = $this->getTrends();

        return [
            Stat::make('Active Shifts', $activeShifts)
                ->description($activeShifts > 0 ? 'Field operations ongoing' : 'All shifts closed')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($trends['shifts']) // FIXED: Trend line now exists for Active Shifts
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
                ->chart($trends['volume'])
                ->color('warning'),

            Stat::make('Total Fees', 'KES ' . number_format($purchaseStats->fees ?? 0, 0))
                ->description('Transaction cost leakage')
                ->descriptionIcon('heroicon-m-credit-card')
                ->chart($trends['fees'])
                ->color('gray'),

            Stat::make('Network Liquidity', 'KES ' . number_format($totalRunningBalance, 0))
                ->description('Total cash in circulation')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($trends['liquidity']) // UPDATED: Now uses Float trend for better accuracy
                ->color('primary'),
        ];
    }

    protected function getTrends(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        // 1. Shift Trends (Count of shifts opened per day)
        $shiftData = Shift::query()
            ->selectRaw('DATE(opened_at) as date, COUNT(*) as qty')
            ->where('opened_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('qty', 'date');

        // 2. Purchase & Fees Trends
        $purchaseData = Purchase::query()
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, SUM(transaction_fee) as fees, COUNT(*) as qty')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // 3. Expense Trends
        $expenseData = Expense::query()
            ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('total', 'date');

        // 4. Liquidity Trend (Based on approved float requests)
        $liquidityData = FloatRequest::query()
            ->where('status', 'approved')
            ->selectRaw('DATE(updated_at) as date, SUM(amount) as total')
            ->where('updated_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('total', 'date');

        return [
            'shifts'    => $days->map(fn ($date) => $shiftData->get($date, 0))->toArray(),
            'purchases' => $days->map(fn ($date) => $purchaseData->get($date)?->total ?? 0)->toArray(),
            'fees'      => $days->map(fn ($date) => $purchaseData->get($date)?->fees ?? 0)->toArray(),
            'volume'    => $days->map(fn ($date) => $purchaseData->get($date)?->qty ?? 0)->toArray(),
            'expenses'  => $days->map(fn ($date) => $expenseData->get($date, 0))->toArray(),
            'liquidity' => $days->map(fn ($date) => $liquidityData->get($date, 0))->toArray(),
        ];
    }
}