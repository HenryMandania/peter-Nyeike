<?php

namespace App\Filament\FieldOperations\Resources\MpesaTransactionResource\Pages;

use App\Filament\FieldOperations\Resources\MpesaTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMpesaTransaction extends CreateRecord
{
    protected static string $resource = MpesaTransactionResource::class;
}
