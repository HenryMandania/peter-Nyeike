<?php

namespace App\Filament\FieldOperations\Resources\SaleResource\Pages;

use App\Filament\FieldOperations\Resources\SaleResource;
use App\Filament\FieldOperations\Resources\SaleResource\Widgets\BusinessOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            BusinessOverview::class,
        ];
    }
}