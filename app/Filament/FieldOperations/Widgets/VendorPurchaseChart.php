<?php

namespace App\Filament\FieldOperations\Widgets;

use App\Models\Purchase;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class VendorPurchaseChart extends ChartWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'Vendor Volume Distribution';
    protected int | string | array $columnSpan = 'md';

    protected function getData(): array
    {
        $data = Purchase::query()
            ->join('vendors', 'purchases.vendor_id', '=', 'vendors.id')
            ->select('vendors.name', DB::raw('SUM(purchases.total_amount) as total'))
            ->groupBy('vendors.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Total Paid to Vendor',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                    ],
                ],
            ],
            'labels' => $data->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'polarArea'; 
    }
}