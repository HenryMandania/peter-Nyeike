<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\FieldOperations\Resources\FloatRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFloatRequests extends ListRecords
{
    protected static string $resource = FloatRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
