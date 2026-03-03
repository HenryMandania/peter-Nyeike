<?php

namespace App\Filament\FieldOperations\Resources\ItemResource\Widgets;

use App\Models\Purchase;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ItemOverview extends BaseWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        // Context: Filtering data specifically for this Item record
        $query = Purchase::where('item_id', $this->record->id);

        // 1. Volume Stats
        $totalStockIn = (clone $query)->sum('quantity');
        $recentStockIn = (clone $query)->where('created_at', '>=', Carbon::now()->subDays(30))->sum('quantity');

        // 2. Price Trends (Current vs Average)
        $latestPrice = (clone $query)->latest()->value('unit_price') ?? 0;
        $avgPrice = (clone $query)->avg('unit_price') ?? 0;
        
        $priceDiff = $latestPrice - $avgPrice;
        $priceTrendIcon = $priceDiff <= 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-arrow-trending-up';
        $priceColor = $priceDiff <= 0 ? 'success' : 'danger'; // Lower price is "Success" for purchases

        // 3. Sparkline Data (Last 10 price points)
        $chartData = (clone $query)->latest()
            ->take(10)
            ->pluck('unit_price')
            ->reverse()
            ->toArray();

        return [
            Stat::make('Total Intake', number_format($totalStockIn, 2))
                ->description(number_format($recentStockIn, 2) . ' in last 30 days')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->icon('heroicon-m-cube'),

            Stat::make('Latest Unit Price', 'KES ' . number_format($latestPrice, 2))
                ->description($priceDiff <= 0 ? 'Below average' : 'Above average')
                ->descriptionIcon($priceTrendIcon)
                ->chart($chartData)
                ->color($priceColor)
                ->icon('heroicon-m-tag'),

            Stat::make('Total Capital Invested', 'KES ' . number_format((clone $query)->sum('total_amount'), 2))
                ->description('Lifetime spend on this item')
                ->descriptionIcon('heroicon-m-presentation-chart-line')
                ->color('primary')
                ->icon('heroicon-m-banknotes'),
        ];
    }
}