<?php

namespace App\Filament\FieldOperations\Resources\PurchaseResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\FieldOperations\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    // Add this method to refresh the table every 5 seconds
    protected function getDefaultTablePollingInterval(): ?string
    {
        return '5s';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PurchaseResource\Widgets\PurchaseOverview::class,
        ];
    }
}