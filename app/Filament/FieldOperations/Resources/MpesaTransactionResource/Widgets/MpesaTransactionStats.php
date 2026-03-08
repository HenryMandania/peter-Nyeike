<?php

namespace App\Filament\FieldOperations\Resources\MpesaTransactionResource\Widgets;

use App\Models\MpesaTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class MpesaTransactionStats extends BaseWidget
{
    protected function getStats(): array
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            Stat::make('Total Transactions', MpesaTransaction::count())
                ->description('All time transactions')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary')
                ->chart([7, 3, 10, 5, 15, 8, 12]),

            Stat::make('Today\'s Volume', 
                MpesaTransaction::whereDate('created_at', $today)->count()
            )
                ->description(
                    'KES ' . number_format(
                        MpesaTransaction::whereDate('created_at', $today)->sum('amount'),
                        2
                    )
                )
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success'),

            Stat::make('This Week', 
                MpesaTransaction::where('created_at', '>=', $thisWeek)->count()
            )
                ->description(
                    'KES ' . number_format(
                        MpesaTransaction::where('created_at', '>=', $thisWeek)->sum('amount'),
                        2
                    )
                )
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('This Month', 
                MpesaTransaction::where('created_at', '>=', $thisMonth)->count()
            )
                ->description(
                    'KES ' . number_format(
                        MpesaTransaction::where('created_at', '>=', $thisMonth)->sum('amount'),
                        2
                    )
                )
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),

            Stat::make('Pending Transactions', 
                MpesaTransaction::where('status', 'requested')->count()
            )
                ->description('Awaiting completion')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),

            Stat::make('Success Rate', 
                round(
                    (MpesaTransaction::where('status', 'completed')->count() / 
                    max(MpesaTransaction::count(), 1)) * 100
                ) . '%'
            )
                ->description(
                    MpesaTransaction::where('status', 'completed')->count() . ' completed'
                )
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}