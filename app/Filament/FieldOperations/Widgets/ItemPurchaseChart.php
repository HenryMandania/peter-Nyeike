<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Purchase;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ItemPurchaseChart extends ChartWidget
{

    protected static ?int $sort = 3;
    protected static ?string $heading = 'Spending by Item / Product';
    protected static ?string $maxHeight = '300px';
    protected int | string | array $columnSpan = 'md';

    protected function getData(): array
    {
        $data = Purchase::query()
            ->join('items', 'purchases.item_id', '=', 'items.id')
            ->select('items.name', DB::raw('SUM(purchases.total_amount) as total'))
            ->groupBy('items.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Purchase Value (KES)',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => '#6366f1', // Indigo
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // Makes it a horizontal bar chart
            'scales' => [
                'x' => ['display' => false], // Hides gridlines for a cleaner look
            ],
        ];
    }
}