<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Widgets;

use App\Models\FloatRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FloatRequestOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $query = FloatRequest::query();
        $totalCount = $query->count();
        $totalAmount = $query->sum('amount');

        $approvedCount = $query->clone()->where('status', 'approved')->count();
        $pendingCount = $query->clone()->where('status', 'pending')->count();
        $rejectedCount = $query->clone()->where('status', 'rejected')->count();

        $approvedRate = $totalCount > 0 ? round(($approvedCount / $totalCount) * 100) : 0;
        $pendingRate = $totalCount > 0 ? round(($pendingCount / $totalCount) * 100) : 0;
        $rejectionRate = $totalCount > 0 ? round(($rejectedCount / $totalCount) * 100, 1) : 0;

        $recentData = FloatRequest::orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->reverse();

        return [

            // 💰 TOTAL FLOAT REQUESTED
            Stat::make(
                'Total Float Requested',
                'KES ' . number_format($totalAmount, 2)
            )
                ->description($totalCount . ' total requests processed')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($recentData->pluck('amount')->toArray())
                ->color('info')
                ->icon('heroicon-m-banknotes'),

            // ✅ APPROVED
            Stat::make(
                'Approved Requests',
                $approvedCount
            )
                ->description($approvedRate . '% approval rate')
                ->descriptionIcon('heroicon-m-check-badge')
                ->chart(
                    $recentData->map(fn ($r) => $r->status === 'approved' ? 1 : 0)->toArray()
                )
                ->color('success')
                ->icon('heroicon-m-hand-thumb-up'),

            // ⏳ PENDING
            Stat::make(
                'Pending Requests',
                $pendingCount
            )
                ->description($pendingRate . '% awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->chart(
                    $recentData->map(fn ($r) => $r->status === 'pending' ? 1 : 0)->toArray()
                )
                ->color('warning')
                ->icon('heroicon-m-arrow-path'),

            // ❌ REJECTION RATE
            Stat::make(
                'Rejection Rate',
                $rejectionRate . '%'
            )
                ->description($rejectedCount . ' rejected requests')
                ->descriptionIcon('heroicon-m-x-circle')
                ->chart(
                    $recentData->map(fn ($r) => $r->status === 'rejected' ? 1 : 0)->toArray()
                )
                ->color('danger')
                ->icon('heroicon-m-shield-exclamation'),
        ];
    }
}