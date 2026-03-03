<?php

namespace App\Filament\FieldOperations\Resources\SupplierResource\Pages;

use App\Filament\FieldOperations\Resources\SupplierResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}