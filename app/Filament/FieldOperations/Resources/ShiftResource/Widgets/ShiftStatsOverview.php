<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\Widgets;

use App\Models\Shift;
use App\Services\BalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ShiftStatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    // Forces the widget container to occupy the full width of the page
    protected int | string | array $columnSpan = 'full';

    // This is the fix for the UI: it forces a 3-column grid on desktop
    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $service = new BalanceService();
        
        $purchaseStats = DB::table('purchases')
            ->selectRaw('SUM(total_amount) as total_val, SUM(transaction_fee) as fees, COUNT(*) as qty')
            ->first();
            
        $totalExpenses = DB::table('expenses')->sum('amount');
        $activeShifts = Shift::where('status', 'open')->count();
        
        $totalRunningBalance = Shift::all()->reduce(fn ($carry, $shift) => $carry + $service->calculate($shift), 0);

        return [
            Stat::make('Active Shifts', $activeShifts)
                ->description('Operators on duty')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($activeShifts > 0 ? 'success' : 'gray')
                ->extraAttributes(['class' => 'ring-1 ring-white/10']),

            Stat::make('Total Purchases Value', 'KES ' . number_format($purchaseStats->total_val ?? 0, 0))
                ->description('Gross inventory spend')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->chart([5, 10, 8, 15, 12, 20, 25])
                ->color('info')
                ->extraAttributes(['class' => 'ring-1 ring-white/10']),

            Stat::make('Total Expenses', 'KES ' . number_format($totalExpenses, 0))
                ->description('Operations & overhead')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart([15, 12, 10, 8, 6, 4, 2])
                ->color('danger')
                ->extraAttributes(['class' => 'ring-1 ring-white/10']),

            Stat::make('Purchases Count', number_format($purchaseStats->qty ?? 0))
                ->description('Total stock transactions')
                ->descriptionIcon('heroicon-m-hashtag')
                ->color('warning')
                ->extraAttributes(['class' => 'ring-1 ring-white/10']),

            Stat::make('Total Fees', 'KES ' . number_format($purchaseStats->fees ?? 0, 0))
                ->description('M-Pesa / Bank charges')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('gray')
                ->extraAttributes(['class' => 'ring-1 ring-white/10']),

            Stat::make('Running Balance', 'KES ' . number_format($totalRunningBalance, 0))
                ->description('Current available cash')
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart([10, 15, 25, 20, 35, 45, 50])
                ->color('primary')
                ->extraAttributes(['class' => 'ring-1 ring-white/10']),
        ];
    }
}