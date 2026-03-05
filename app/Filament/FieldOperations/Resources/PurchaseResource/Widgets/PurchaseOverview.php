<?php

namespace App\Filament\FieldOperations\Resources\PurchaseResource\Widgets;

use App\Models\Purchase;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PurchaseOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    // This forces the grid to have 4 columns on desktop screens
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        // --- Purchase Metrics ---
        $totalWeight = Purchase::sum('quantity');
        $averagePrice = Purchase::avg('unit_price') ?? 0;
        $totalFees = Purchase::sum('transaction_fee');
        $grandTotal = Purchase::sum('total_amount');

        // --- Sales & Profit Metrics ---
        $totalSales = Purchase::where('is_sold', true)->sum('sales_amount');
        $totalProfit = Purchase::where('is_sold', true)->sum('gross_profit');
        $pendingSalesCount = Purchase::where('is_sold', false)->whereNotNull('approved_by')->count();
        $avgProfitMargin = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;

        $charts = $this->getChartData();

        return [
            // Row 1: Purchases
            Stat::make('Total Weight', number_format($totalWeight, 2))
                ->description('Sum of all weights')
                ->descriptionIcon('heroicon-m-scale')
                ->chart($charts['weight'])
                ->color('info')
                ->icon('heroicon-m-scale'),

            Stat::make('Average Unit Cost', 'KES ' . number_format($averagePrice, 2))
                ->description('Mean cost per unit')
                ->chart($charts['avg_price'])
                ->color('primary')
                ->icon('heroicon-m-tag'),

            Stat::make('Total Fees', 'KES ' . number_format($totalFees, 2))
                ->description('Transaction charges')
                ->chart($charts['fees'])
                ->color('warning') // Changed from danger for better UI feel
                ->icon('heroicon-m-banknotes'),

            Stat::make('Grand Total Cost', 'KES ' . number_format($grandTotal, 2))
                ->description('Total purchase value')
                ->chart($charts['total'])
                ->color('gray')
                ->icon('heroicon-m-shopping-bag'),

            // Row 2: Sales & Performance
            Stat::make('Total Sales', 'KES ' . number_format($totalSales, 2))
                ->description('Revenue from sold items')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($charts['sales'])
                ->color('success')
                ->icon('heroicon-m-currency-dollar'),

            Stat::make('Net Profit', 'KES ' . number_format($totalProfit, 2))
                ->description('Revenue minus total cost')
                ->chart($charts['profit'])
                ->color($totalProfit >= 0 ? 'success' : 'danger')
                ->icon('heroicon-m-presentation-chart-line'),

            Stat::make('Profit Margin', number_format($avgProfitMargin, 1) . '%')
                ->description('Avg profitability percentage')
                ->color('primary')
                ->icon('heroicon-m-chart-pie'),

            Stat::make('Pending Sales', $pendingSalesCount)
                ->description('Approved items not yet sold')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingSalesCount > 0 ? 'warning' : 'gray')
                ->icon('heroicon-m-shopping-cart'),
        ];
    }

    protected function getChartData(): array
    {
        $data = Purchase::select([
                DB::raw('SUM(quantity) as weight'),
                DB::raw('SUM(transaction_fee) as fees'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('AVG(unit_price) as avg_price'),
                DB::raw('SUM(sales_amount) as sales'),
                DB::raw('SUM(gross_profit) as profit'),
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
                'sales'     => $defaults,
                'profit'    => $defaults,
            ];
        }

        return [
            'weight'    => $data->pluck('weight')->toArray(),
            'fees'      => $data->pluck('fees')->toArray(),
            'total'     => $data->pluck('total')->toArray(),
            'avg_price' => $data->pluck('avg_price')->toArray(),
            'sales'     => $data->pluck('sales')->toArray(),
            'profit'    => $data->pluck('profit')->toArray(),
        ];
    }
}