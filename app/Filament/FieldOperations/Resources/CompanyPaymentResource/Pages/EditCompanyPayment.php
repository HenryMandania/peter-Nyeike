<?php

namespace App\Filament\FieldOperations\Resources\CompanyPaymentResource\Pages;

use App\Filament\FieldOperations\Resources\CompanyPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCompanyPayment extends EditRecord
{
    protected static string $resource = CompanyPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
           
        ];
    }
}
