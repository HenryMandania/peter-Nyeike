<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Expense;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpenseTypeChart extends ChartWidget
{
    protected static ?string $heading = 'Expense Distribution by Category';
    
    // This allows the chart to refresh if data changes
    protected static ?string $pollingInterval = '30s';

    // Controls the size of the chart on the dashboard (1/2 width)
    protected int | string | array $columnSpan = 'md';

    protected function getData(): array
    {
        // Pushing information: Aggregate expense amounts grouped by their category name
        $data = Expense::query()
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Spent (KES)',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6', // blue
                        '#10b981', // emerald
                        '#f59e0b', // amber
                        '#ef4444', // red
                        '#8b5cf6', // violet
                        '#ec4899', // pink
                    ],
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; // A doughnut chart looks more modern/pretty than a standard pie
    }

    /**
     * Optional: Add extra "intelligence" by adding a description 
     * based on the highest spending area.
     */
    public function getDescription(): ?string
    {
        $highest = Expense::query()
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name', DB::raw('SUM(expenses.amount) as total'))
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->first();

        return $highest 
            ? "Your highest spending is currently in: {$highest->name}" 
            : "No expense data recorded yet.";
    }
}