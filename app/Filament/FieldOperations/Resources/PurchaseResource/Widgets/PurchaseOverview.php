<?php

namespace App\Filament\FieldOperations\Resources\PurchaseResource\Widgets;

use App\Models\Purchase;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\FieldOperations\Resources\PurchaseResource\Pages\ListPurchases;
use Illuminate\Support\Facades\DB;

class PurchaseOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListPurchases::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        
        // Fetch chart data
        $charts = $this->getChartData();

        return [
            Stat::make('Total Weight', number_format($query->sum('quantity'), 2))
                ->description('Sum of all weights')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($charts['weight']) // Ensure this is a simple array [1, 5, 3, ...]
                ->color('info')
                ->icon('heroicon-m-scale'),

            Stat::make('Average Unit Price', 'KES ' . number_format($query->avg('unit_price') ?? 0, 2))
                ->description('Mean price per unit')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('primary')
                ->icon('heroicon-m-tag'),

            Stat::make('Total Fees', 'KES ' . number_format($query->sum('transaction_fee'), 2))
                ->description('Sum of transaction charges')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($charts['fees'])
                ->color('danger')
                ->icon('heroicon-m-banknotes'),

            Stat::make('Grand Total', 'KES ' . number_format($query->sum('total_amount'), 2))
                ->description('Total purchase value')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($charts['total'])
                ->color('success')
                ->icon('heroicon-m-currency-dollar'),
        ];
    }

    protected function getChartData(): array
    {
        // Get data grouped by date for the last 10 entries to show a visible trend
        $data = Purchase::select([
                DB::raw('SUM(quantity) as weight'),
                DB::raw('SUM(transaction_fee) as fees'),
                DB::raw('SUM(total_amount) as total'),
                'created_at'
            ])
            ->groupBy('created_at')
            ->orderBy('created_at', 'asc')
            ->limit(10) 
            ->get();

        // If no data exists, return a flat line so the chart area initializes
        if ($data->isEmpty()) {
            return [
                'weight' => [0, 0, 0, 0],
                'fees'   => [0, 0, 0, 0],
                'total'  => [0, 0, 0, 0],
            ];
        }

        return [
            'weight' => $data->pluck('weight')->toArray(),
            'fees'   => $data->pluck('fees')->toArray(),
            'total'  => $data->pluck('total')->toArray(),
        ];
    }
}