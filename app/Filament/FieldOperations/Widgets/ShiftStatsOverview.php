<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Shift;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\FloatRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ShiftStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalInflow = Shift::sum('opening_balance') + FloatRequest::where('status', 'approved')->sum('amount');
        
        
        $totalOutflow = Purchase::sum('total_amount') + Expense::sum('amount');

        return [
            Stat::make('Network Liquidity', 'KES ' . number_format($totalInflow, 2))
                ->description('Total cash currently in circulation')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Total Procurement', 'KES ' . number_format(Purchase::sum('total_amount'), 2))
                ->description('Value of goods purchased')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('info'),

            Stat::make('Operational Overhead', 'KES ' . number_format(Expense::sum('amount'), 2))
                ->description('Total shift expenses recorded')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger'),

            Stat::make('Active Shifts', Shift::where('status', 'open')->count())
                ->description('Operators currently in the field')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}