<?php

namespace App\Filament\FieldOperations\Resources\SupplierResource\Widgets;

use App\Models\Purchase;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SupplierOverview extends BaseWidget
{
    // Essential for record-specific data on the Edit Page
    public ?Model $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        // Filter data specifically for this Vendor/Supplier
        $query = Purchase::where('vendor_id', $this->record->id);

        // 1. Transaction Volume Logic
        $totalOrders = (clone $query)->count();
        $recentOrders = (clone $query)->where('created_at', '>=', Carbon::now()->subDays(30))->count();

        // 2. Financial Trends (Last 10 payments)
        $totalSpent = (clone $query)->sum('total_amount');
        $paymentHistory = (clone $query)->latest()
            ->take(10)
            ->pluck('total_amount')
            ->reverse()
            ->toArray();

        // 3. Performance Metric (Average Lead Time or Weight)
        $totalWeight = (clone $query)->sum('quantity');
        $avgWeightPerOrder = $totalOrders > 0 ? $totalWeight / $totalOrders : 0;

        return [
            Stat::make('Partnership Value', 'KES ' . number_format($totalSpent, 2))
                ->description('Lifetime transaction volume')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart($paymentHistory)
                ->color('success')
                ->icon('heroicon-m-currency-dollar'),

            Stat::make('Fulfillment Volume', number_format($totalWeight, 2) . ' units')
                ->description('Total quantity delivered')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info')
                ->icon('heroicon-m-scale'),

            Stat::make('Order Consistency', $totalOrders . ' Total Orders')
                ->description($recentOrders . ' orders in the last 30 days')
                ->descriptionIcon($recentOrders > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-minus')
                ->color($recentOrders > 0 ? 'primary' : 'gray')
                ->icon('heroicon-m-shopping-bag'),
        ];
    }
}