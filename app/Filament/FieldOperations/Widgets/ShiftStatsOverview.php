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
        $openingBalances = (float) Shift::sum('opening_balance');
        $approvedFloats = (float) FloatRequest::where('status', 'approved')->sum('amount');
        $totalInflow = $openingBalances + $approvedFloats;
        
        $totalPurchases = (float) Purchase::sum('total_amount');
        $totalExpenses = (float) Expense::sum('amount');
        $activeShiftsCount = Shift::where('status', 'open')->count();

        return [
            Stat::make('Network Liquidity', 'KES ' . number_format($totalInflow, 2))
                ->description('Total cash flow in network')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            Stat::make('Total Procurement', 'KES ' . number_format($totalPurchases, 2))
                ->description('Value of inventory acquired')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->chart([15, 4, 10, 2, 12, 4, 11])
                ->color('info'),

            Stat::make('Operational Overhead', 'KES ' . number_format($totalExpenses, 2))
                ->description('Cumulative field expenses')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([2, 10, 5, 12, 4, 17, 4])
                ->color($totalExpenses > ($totalInflow * 0.2) ? 'danger' : 'warning'),

            Stat::make('Active Field Shifts', $activeShiftsCount)
                ->description($activeShiftsCount > 0 ? 'Operations in progress' : 'No active field units')
                ->descriptionIcon('heroicon-m-bolt')
                ->chart([1, 3, 2, 5, 4, 6, $activeShiftsCount])
                ->color($activeShiftsCount > 0 ? 'primary' : 'gray'),
        ];
    }
}