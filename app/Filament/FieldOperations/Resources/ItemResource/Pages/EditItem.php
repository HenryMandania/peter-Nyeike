<?php

namespace App\Filament\FieldOperations\Resources\ItemResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\FieldOperations\Resources\ItemResource;
use App\Filament\FieldOperations\Resources\ItemResource\Widgets\ItemOverview;
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

    protected function getHeaderWidgets(): array
    {
        return [
            ItemOverview::class,
        ];
    }
}