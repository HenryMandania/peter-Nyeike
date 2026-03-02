<?php

namespace App\Filament\FieldOperations\Resources\ItemResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\FieldOperations\Resources\ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
