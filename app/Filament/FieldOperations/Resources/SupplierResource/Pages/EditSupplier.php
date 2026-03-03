<?php

namespace App\Filament\FieldOperations\Resources\SupplierResource\Pages;

use App\Filament\FieldOperations\Resources\SupplierResource;
use App\Filament\FieldOperations\Resources\SupplierResource\Widgets\SupplierOverview;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SupplierOverview::class,
        ];
    }
}