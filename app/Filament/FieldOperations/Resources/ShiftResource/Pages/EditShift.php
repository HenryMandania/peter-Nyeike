<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\FieldOperations\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShift extends EditRecord
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
