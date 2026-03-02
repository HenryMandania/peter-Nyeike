<?php

namespace App\Filament\FieldOperations\Resources\PurchaseResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\FieldOperations\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Only show the edit button on the view page if the record is NOT approved
            EditAction::make()
                ->visible(fn ($record) => $record->approved_by === null),
        ];
    }
}