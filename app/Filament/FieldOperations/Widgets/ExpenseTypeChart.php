<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Expense;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExpenseTypeChart extends ChartWidget
{

    protected static ?int $sort = 4;
    protected static ?string $heading = 'Expense Distribution by Category';
    
    
    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'md';

    protected function getData(): array
    {
        
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
                        '#3b82f6', 
                        '#10b981', 
                        '#f59e0b', 
                        '#ef4444', 
                        '#8b5cf6', 
                        '#ec4899', 
                    ],
                    'hoverOffset' => 10,
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';  
    }

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