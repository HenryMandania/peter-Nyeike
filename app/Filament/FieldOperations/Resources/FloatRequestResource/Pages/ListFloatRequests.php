<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;

use App\Filament\FieldOperations\Resources\FloatRequestResource;
use Filament\Actions\CreateAction;
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
     
    protected function getHeaderWidgets(): array
    {
        return [
            FloatRequestResource\Widgets\FloatRequestOverview::class,
        ];
    }
}