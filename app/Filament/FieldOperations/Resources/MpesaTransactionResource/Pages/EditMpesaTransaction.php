<?php

namespace App\Filament\FieldOperations\Resources\MpesaTransactionResource\Pages;

use App\Filament\FieldOperations\Resources\MpesaTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMpesaTransaction extends EditRecord
{
    protected static string $resource = MpesaTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
