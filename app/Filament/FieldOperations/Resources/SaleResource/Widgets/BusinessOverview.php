<?php

namespace App\Filament\FieldOperations\Resources\SaleResource\Widgets;

use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class BusinessOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $from = request('from');
        $until = request('until');
        $supplierId = request('supplier_id');
        $itemId = request('item_id');
        $companyId = request('company_id');

        $salesQuery = Sale::query()
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($until, fn($q) => $q->whereDate('created_at', '<=', $until))
            ->when($supplierId, fn($q) => $q->whereHas('purchase', fn($q2) => $q2->where('vendor_id', $supplierId)))
            ->when($itemId, fn($q) => $q->whereHas('purchase', fn($q2) => $q2->where('item_id', $itemId)))
            ->when($companyId, fn($q) => $q->where('company_id', $companyId));

        $purchaseIds = (clone $salesQuery)->pluck('purchase_id')->toArray();
        $purchasesQuery = Purchase::whereIn('id', $purchaseIds);

        // Fetch pre-aggregated data for the last 7 days
        $trendData = $this->getTrendData($salesQuery, $purchasesQuery);

        return [
            Stat::make('Fruit Purchases', 'KES ' . number_format($purchasesQuery->sum('fruit_cost'), 2))
                ->description('Total fruit buying cost')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->chart($trendData['purchases'])
                ->color('warning'),

            Stat::make('Total Revenue', 'KES ' . number_format($salesQuery->sum('sales_amount'), 2))
                ->description('All sales value')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($trendData['sales'])
                ->color('success'),

            Stat::make('Net Profit', 'KES ' . number_format($salesQuery->sum('profit'), 2))
                ->description('Revenue minus cost')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($trendData['profit'])
                ->color('primary'),

            Stat::make('Today Sales', 'KES ' . number_format((clone $salesQuery)->whereDate('created_at', today())->sum('sales_amount'), 2))
                ->description('Today performance')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($trendData['today_sales'])
                ->color('info'),
        ];
    }

    protected function getTrendData(Builder $salesQuery, Builder $purchasesQuery): array
    {
        $startDate = Carbon::today()->subDays(6);

        // Aggregate Sales Data
        $salesTrend = (clone $salesQuery)
            ->selectRaw('DATE(created_at) as date, SUM(sales_amount) as total_sales, SUM(profit) as total_profit')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->pluck('total_sales', 'date');

        $profitTrend = (clone $salesQuery)
            ->selectRaw('DATE(created_at) as date, SUM(profit) as total_profit')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->pluck('total_profit', 'date');

        // Aggregate Purchase Data
        $purchasesTrend = (clone $purchasesQuery)
            ->selectRaw('DATE(created_at) as date, SUM(fruit_cost) as total_cost')
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->pluck('total_cost', 'date');

        // Map to 7-day array
        return collect(range(6, 0))->mapWithKeys(function ($i) use ($salesTrend, $profitTrend, $purchasesTrend, $startDate) {
            $date = Carbon::today()->subDays($i)->format('Y-m-d');
            return [$date => [
                'sales' => $salesTrend->get($date, 0),
                'profit' => $profitTrend->get($date, 0),
                'purchases' => $purchasesTrend->get($date, 0),
            ]];
        })->reduce(function ($acc, $item) {
            $acc['sales'][] = $item['sales'];
            $acc['profit'][] = $item['profit'];
            $acc['purchases'][] = $item['purchases'];
            $acc['today_sales'][] = $item['sales']; // Logic for today's chart slice
            return $acc;
        }, ['sales' => [], 'profit' => [], 'purchases' => [], 'today_sales' => []]);
    }
}