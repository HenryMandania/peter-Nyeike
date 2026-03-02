<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\FieldOperations\Resources\FloatRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFloatRequest extends EditRecord
{
    protected static string $resource = FloatRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
