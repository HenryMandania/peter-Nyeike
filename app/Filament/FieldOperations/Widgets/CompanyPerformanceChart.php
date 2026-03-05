<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Purchase;
use Filament\Widgets\ChartWidget;

class CompanyPerformanceChart extends ChartWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Revenue by Company';
    protected static ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $data = Purchase::query()
            ->join('companies', 'purchases.company_id', '=', 'companies.id')
            ->where('is_sold', true)
            ->selectRaw('companies.name, SUM(sales_amount) as total')
            ->groupBy('companies.name')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'],
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }
}