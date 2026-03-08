<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\Widgets;

use App\Models\Shift;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\FloatRequest;
use App\Services\BalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ShiftStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';
    protected int | string | array $columnSpan = 'full';

    // Set this to 4 to ensure 4 widgets per row
    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $service = new BalanceService();
        
        $purchaseStats = Purchase::query()
            ->selectRaw('SUM(total_amount) as total_val, SUM(transaction_fee) as fees, COUNT(*) as qty, SUM(sales_amount) as total_sales, SUM(gross_profit) as total_profit')
            ->first();
            
        $totalExpenses = Expense::sum('amount');
        $activeShifts = Shift::where('status', 'open')->count();
        
        // Total Liquidity across all shifts
        $totalRunningBalance = Shift::all()->reduce(fn ($carry, $shift) => $carry + $service->calculate($shift), 0);

        $trends = $this->getTrends();

        return [
            // ROW 1
            Stat::make('Active Shifts', $activeShifts)
                ->description($activeShifts > 0 ? 'Operations ongoing' : 'All closed')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($trends['shifts'])
                ->color($activeShifts > 0 ? 'success' : 'gray')
                ->icon('heroicon-m-clock'),

            Stat::make('Total Purchases', 'KES ' . number_format($purchaseStats->total_val ?? 0, 0))
                ->description('Inventory investment')
                ->chart($trends['purchases'])
                ->color('info')
                ->icon('heroicon-m-shopping-bag'),

            Stat::make('Total Sales', 'KES ' . number_format($purchaseStats->total_sales ?? 0, 0))
                ->description('Total revenue generated')
                ->chart($trends['sales'])
                ->color('success')
                ->icon('heroicon-m-currency-dollar'),

            Stat::make('Net Profit', 'KES ' . number_format($purchaseStats->total_profit ?? 0, 0))
                ->description('Revenue vs Total Cost')
                ->chart($trends['profit'])
                ->color($purchaseStats->total_profit >= 0 ? 'success' : 'danger')
                ->icon('heroicon-m-presentation-chart-line'),

            // ROW 2
            Stat::make('Total Expenses', 'KES ' . number_format($totalExpenses, 0))
                ->description('Operational overhead')
                ->chart($trends['expenses'])
                ->color('danger')
                ->icon('heroicon-m-arrow-trending-down'),

            Stat::make('Purchase Volume', number_format($purchaseStats->qty ?? 0) . ' txns')
                ->description('Procurement count')
                ->chart($trends['volume'])
                ->color('warning')
                ->icon('heroicon-m-hashtag'),

            Stat::make('Total Fees', 'KES ' . number_format($purchaseStats->fees ?? 0, 0))
                ->description('Transaction leakage')
                ->chart($trends['fees'])
                ->color('gray')
                ->icon('heroicon-m-credit-card'),

            Stat::make('Network Liquidity', 'KES ' . number_format($totalRunningBalance, 0))
                ->description('Cash in circulation')
                ->chart($trends['liquidity'])
                ->color('primary')
                ->icon('heroicon-m-banknotes'),
        ];
    }

    protected function getTrends(): array
    {
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        // 1. Shift Trends
        $shiftData = Shift::query()
            ->selectRaw('DATE(opened_at) as date, COUNT(*) as qty')
            ->where('opened_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('qty', 'date');

        // 2. Purchase, Sales, Profit & Fees Trends
        $purchaseData = Purchase::query()
            ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, SUM(transaction_fee) as fees, COUNT(*) as qty, SUM(sales_amount) as sales, SUM(gross_profit) as profit')
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

        // 4. Liquidity Trend
        $liquidityData = FloatRequest::query()
            ->where('status', 'approved')
            ->selectRaw('DATE(updated_at) as date, SUM(amount) as total')
            ->where('updated_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->pluck('total', 'date');

        return [
            'shifts'    => $days->map(fn ($date) => $shiftData->get($date, 0))->toArray(),
            'purchases' => $days->map(fn ($date) => $purchaseData->get($date)?->total ?? 0)->toArray(),
            'sales'     => $days->map(fn ($date) => $purchaseData->get($date)?->sales ?? 0)->toArray(),
            'profit'    => $days->map(fn ($date) => $purchaseData->get($date)?->profit ?? 0)->toArray(),
            'fees'      => $days->map(fn ($date) => $purchaseData->get($date)?->fees ?? 0)->toArray(),
            'volume'    => $days->map(fn ($date) => $purchaseData->get($date)?->qty ?? 0)->toArray(),
            'expenses'  => $days->map(fn ($date) => $expenseData->get($date, 0))->toArray(),
            'liquidity' => $days->map(fn ($date) => $liquidityData->get($date, 0))->toArray(),
        ];
    }
}
