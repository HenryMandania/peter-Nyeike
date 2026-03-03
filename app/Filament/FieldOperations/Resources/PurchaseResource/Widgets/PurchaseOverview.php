<?php

namespace App\Filament\FieldOperations\Resources\PurchaseResource\Widgets;

use App\Models\Purchase;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PurchaseOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        // Use a fresh query for totals to ensure they aren't affected by the chart limit
        $totalWeight = Purchase::sum('quantity');
        $averagePrice = Purchase::avg('unit_price') ?? 0;
        $totalFees = Purchase::sum('transaction_fee');
        $grandTotal = Purchase::sum('total_amount');

        $charts = $this->getChartData();

        return [
            Stat::make('Total Weight', number_format($totalWeight, 2))
                ->description('Sum of all weights')
                ->descriptionIcon('heroicon-m-scale')
                ->chart($charts['weight'])
                ->color('info')
                ->icon('heroicon-m-scale'),

            Stat::make('Average Unit Price', 'KES ' . number_format($averagePrice, 2))
                ->description('Mean price per unit')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->chart($charts['avg_price']) // Added the missing trend line
                ->color('primary')
                ->icon('heroicon-m-tag'),

            Stat::make('Total Fees', 'KES ' . number_format($totalFees, 2))
                ->description('Sum of transaction charges')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($charts['fees'])
                ->color('danger')
                ->icon('heroicon-m-banknotes'),

            Stat::make('Grand Total', 'KES ' . number_format($grandTotal, 2))
                ->description('Total purchase value')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart($charts['total'])
                ->color('success')
                ->icon('heroicon-m-currency-dollar'),
        ];
    }

    protected function getChartData(): array
    {
        // Fetch last 7 days of activity
        $data = Purchase::select([
                DB::raw('SUM(quantity) as weight'),
                DB::raw('SUM(transaction_fee) as fees'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('AVG(unit_price) as avg_price'),
                DB::raw('DATE(created_at) as date')
            ])
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        if ($data->isEmpty()) {
            $defaults = array_fill(0, 7, 0);
            return [
                'weight'    => $defaults,
                'fees'      => $defaults,
                'total'     => $defaults,
                'avg_price' => $defaults,
            ];
        }

        return [
            'weight'    => $data->pluck('weight')->toArray(),
            'fees'      => $data->pluck('fees')->toArray(),
            'total'     => $data->pluck('total')->toArray(),
            'avg_price' => $data->pluck('avg_price')->toArray(),
        ];
    }
}