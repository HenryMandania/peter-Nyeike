<?php

namespace App\Filament\FieldOperations\Resources\SupplierResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\FieldOperations\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
