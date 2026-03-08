<?php

namespace App\Filament\FieldOperations\Resources\CompanyPaymentResource\Pages;

use App\Filament\FieldOperations\Resources\CompanyPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCompanyPayment extends ViewRecord
{
    protected static string $resource = CompanyPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}