<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Purchase;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SalesOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // 1. Calculate Aggregate Totals
        $salesData = Purchase::query()
            ->where('is_sold', true)
            ->selectRaw('SUM(sales_amount) as revenue, SUM(gross_profit) as profit, COUNT(*) as count')
            ->first();

        $revenue = $salesData->revenue ?? 0;
        $profit = $salesData->profit ?? 0;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        // 2. Fetch Trend Data for Sparklines (Last 7 Days)
        // We fetch the last 7 days including today
        $rawTrends = Purchase::query()
            ->where('is_sold', true)
            ->where('sold_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(sold_at) as date, SUM(sales_amount) as daily_revenue, SUM(gross_profit) as daily_profit, COUNT(*) as daily_count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        /**
         * 3. Ensure a 7-day trend line even if days have 0 sales.
         * Filament charts require an array of values. Missing dates in the DB 
         * will result in empty charts unless we fill the gaps with 0.
         */
        $trendData = collect(range(6, 0))->mapWithKeys(function ($days) use ($rawTrends) {
            $date = now()->subDays($days)->format('Y-m-d');
            
            // If data exists for the date, use it; otherwise, provide defaults
            $entry = $rawTrends->get($date) ?? (object)[
                'daily_revenue' => 0,
                'daily_profit' => 0,
                'daily_count' => 0,
            ];

            return [$date => $entry];
        });

        return [
            Stat::make('Total Revenue', 'KES ' . number_format($revenue, 0))
                ->description('Total sales generated')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($trendData->pluck('daily_revenue')->toArray())
                ->icon('heroicon-o-currency-dollar')
                ->color('success'),

            Stat::make('Net Profit', 'KES ' . number_format($profit, 0))
                ->description('Earnings after costs')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($trendData->pluck('daily_profit')->toArray())
                ->icon('heroicon-o-presentation-chart-line')
                ->color('primary'),

            Stat::make('Profit Margin', number_format($margin, 1) . '%')
                ->description($margin >= 20 ? 'High profitability' : 'Normal margin')
                ->descriptionIcon($margin >= 20 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-chart-pie')
                // Calculate dynamic margin per day for the chart
                ->chart($trendData->map(function($d) {
                    return $d->daily_revenue > 0 ? ($d->daily_profit / $d->daily_revenue) * 100 : 0;
                })->toArray())
                ->icon('heroicon-o-receipt-percent')
                ->color($margin > 15 ? 'success' : 'warning'),

            Stat::make('Sales Count', number_format($salesData->count))
                ->description('Total items sold')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->chart($trendData->pluck('daily_count')->toArray())
                ->icon('heroicon-o-shopping-bag')
                ->color('info'),
        ];
    }
}