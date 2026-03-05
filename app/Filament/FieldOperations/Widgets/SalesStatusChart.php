<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Purchase;
use Filament\Widgets\ChartWidget;

class SalesStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Inventory vs Sales Status';
    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $sold = Purchase::where('is_sold', true)->count();
        $pending = Purchase::where('is_sold', false)->whereNotNull('approved_by')->count();
        $unapproved = Purchase::whereNull('approved_by')->count();

        return [
            'datasets' => [
                [
                    'data' => [$sold, $pending, $unapproved],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#6b7280'],
                ],
            ],
            'labels' => ['Sold', 'Pending Sale', 'In Stock'],
        ];
    }
}