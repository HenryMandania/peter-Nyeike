<?php

namespace App\Filament\FieldOperations\Resources\ShiftResource\Pages;

use App\Filament\FieldOperations\Resources\ShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShifts extends ListRecords
{
    protected static string $resource = ShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ShiftResource\Widgets\ShiftStatsOverview::class,
        ];
    }
}