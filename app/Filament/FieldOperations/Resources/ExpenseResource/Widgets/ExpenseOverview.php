<?php

namespace App\Filament\FieldOperations\Resources\ExpenseResource\Widgets;

use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\FieldOperations\Resources\ExpenseResource\Pages\ListExpenses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
        // Inherits the permission-scoped query from ListExpenses
        $query = $this->getPageTableQuery();
        
        // 1. Trend data: Scoped and Reordered for the sparkline
        // We reorder() to ensure the chart isn't affected by table sorting
        $expenseTrend = (clone $query)
            ->reorder() 
            ->orderBy('created_at', 'desc')
            ->limit(12)
            ->pluck('amount')
            ->reverse()
            ->toArray();

        // 2. Top Category Logic: Scoped and Reordered
        // Fixes: "Expression #1 of ORDER BY clause is not in GROUP BY clause"
        $topCategory = (clone $query)
            ->reorder() 
            ->select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_category_id')
            ->with('category')
            ->orderByDesc('total')
            ->first();

        return [
            // 💰 TOTAL EXPENSES
            Stat::make('Total Expenses', 'KES ' . number_format($query->sum('amount'), 2))
                ->description('Total outflow for this view')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart($expenseTrend ?: [0, 0])
                ->color('danger')
                ->icon('heroicon-m-banknotes'),

            // 🧮 AVERAGE TRANSACTION
            Stat::make('Avg. Transaction', 'KES ' . number_format($query->avg('amount') ?? 0, 2))
                ->description('Average cost per expense')
                ->descriptionIcon('heroicon-m-calculator')
                ->chart($expenseTrend ?: [0, 0])
                ->color('primary')
                ->icon('heroicon-m-receipt-percent'),

            // 🏷️ HIGHEST CATEGORY
            Stat::make('Highest Category', $topCategory?->category?->name ?? 'None')
                ->description($topCategory ? 'KES ' . number_format($topCategory->total, 2) . ' total' : 'No expenses recorded')
                ->descriptionIcon('heroicon-m-tag')
                ->chart($expenseTrend ?: [0, 0])
                ->color('warning')
                ->icon('heroicon-m-funnel'),

            // 📋 RECORD COUNT
            Stat::make('Record Count', $query->count())
                ->description('Individual receipts filed')
                ->descriptionIcon('heroicon-m-list-bullet')
                ->chart($expenseTrend ?: [0, 0])
                ->color('info')
                ->icon('heroicon-m-hashtag'),
        ];
    }
}