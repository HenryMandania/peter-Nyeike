<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Widgets;

use App\Models\FloatRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\FieldOperations\Resources\FloatRequestResource\Pages\ListFloatRequests;
use Illuminate\Support\Facades\DB;

class FloatRequestOverview extends BaseWidget
{
    use InteractsWithPageTable;

    // Polling makes the "Waiting..." status feel real-time
    protected static ?string $pollingInterval = '15s';

    protected function getTablePage(): string
    {
        return ListFloatRequests::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        $totalCount = $query->count();

        // Data for trend lines
        $charts = $this->getAdvancedChartData();

        return [
            Stat::make('Total Requested', 'KES ' . number_format($query->sum('amount'), 2))
                ->description('Market demand trend')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($charts['amounts'])
                ->color('info')
                ->icon('heroicon-m-banknotes'),

            Stat::make('Approved Requests', $query->clone()->where('status', 'approved')->count())
                ->description('Disbursed float')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart($charts['approved'])
                ->color('success')
                ->icon('heroicon-m-hand-thumb-up'),

            Stat::make('Pending Approval', $query->clone()->where('status', 'pending')->count())
                ->description($totalCount > 0 ? round(($query->clone()->where('status', 'pending')->count() / $totalCount) * 100) . '% of total queue' : 'Queue empty')
                ->descriptionIcon('heroicon-m-clock')
                ->chart($charts['pending'])
                ->color('warning')
                ->icon('heroicon-m-arrow-path'),
                
            Stat::make('Rejection Rate', $this->getRejectionRate($query))
                ->description('Quality of requests')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->icon('heroicon-m-shield-exclamation'),
        ];
    }

   
    protected function getAdvancedChartData(): array
    {
        
        $recentData = FloatRequest::orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->reverse();

        return [
            'amounts'  => $recentData->pluck('amount')->toArray(),
            'approved' => $recentData->map(fn ($r) => $r->status === 'approved' ? 1 : 0)->toArray(),
            'pending'  => $recentData->map(fn ($r) => $r->status === 'pending' ? 1 : 0)->toArray(),
        ];
    }

    protected function getRejectionRate($query): string
    {
        $total = $query->count();
        if ($total === 0) return '0%';
        
        $rejected = $query->clone()->where('status', 'rejected')->count();
        return number_format(($rejected / $total) * 100, 1) . '%';
    }
}