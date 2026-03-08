<?php

namespace App\Filament\FieldOperations\Resources\MpesaTransactionResource\Pages;

use App\Filament\FieldOperations\Resources\MpesaTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMpesaTransaction extends ViewRecord
{
    protected static string $resource = MpesaTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}