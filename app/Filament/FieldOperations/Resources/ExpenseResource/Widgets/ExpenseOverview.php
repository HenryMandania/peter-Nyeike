<?php

namespace App\Filament\FieldOperations\Resources\ExpenseResource\Widgets;

use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\FieldOperations\Resources\ExpenseResource\Pages\ListExpenses;
use Illuminate\Support\Facades\DB;

class ExpenseOverview extends BaseWidget
{
    use InteractsWithPageTable;

    public array $tableColumnSearches = [];

    protected static ?string $pollingInterval = '15s';

    protected function getTablePage(): string
    {
        return ListExpenses::class;
    }

    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        
        // Trend data: Last 12 expense amounts for the sparklines
        $expenseTrend = Expense::orderBy('created_at', 'desc')
            ->limit(12)
            ->pluck('amount')
            ->reverse()
            ->toArray();

        // Top Category Logic
        $topCategory = Expense::query()
            ->select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_category_id')
            ->with('category')
            ->orderByDesc('total')
            ->first();

        return [
            Stat::make('Total Expenses', 'KES ' . number_format($query->sum('amount'), 2))
                ->description('Total outflow for this view')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart($expenseTrend ?? [0, 0])
                ->color('danger')
                ->icon('heroicon-m-banknotes'),

            Stat::make('Avg. Transaction', 'KES ' . number_format($query->avg('amount') ?? 0, 2))
                ->description('Average cost per expense')
                ->descriptionIcon('heroicon-m-calculator')
                ->chart($expenseTrend ?? [0, 0]) // Added trend line
                ->color('primary')
                ->icon('heroicon-m-receipt-percent'),

            Stat::make('Highest Category', $topCategory?->category?->name ?? 'None')
                ->description($topCategory ? 'KES ' . number_format($topCategory->total, 2) . ' total' : 'No expenses recorded')
                ->descriptionIcon('heroicon-m-tag')
                ->chart($expenseTrend ?? [0, 0]) // Added trend line
                ->color('warning')
                ->icon('heroicon-m-funnel'),

            Stat::make('Record Count', $query->count())
                ->description('Individual receipts filed')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->chart($expenseTrend ?? [0, 0]) // Added trend line
                ->color('info')
                ->icon('heroicon-m-hashtag'),
        ];
    }
}