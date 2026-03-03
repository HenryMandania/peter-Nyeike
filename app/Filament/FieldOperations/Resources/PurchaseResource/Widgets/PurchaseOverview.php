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
        $query = Purchase::query();

        $totalWeight = $query->sum('quantity');
        $averagePrice = $query->avg('unit_price') ?? 0;
        $totalFees = $query->sum('transaction_fee');
        $grandTotal = $query->sum('total_amount');

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
        $data = Purchase::select([
                DB::raw('SUM(quantity) as weight'),
                DB::raw('SUM(transaction_fee) as fees'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('DATE(created_at) as date')
            ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->limit(10)
            ->get();

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