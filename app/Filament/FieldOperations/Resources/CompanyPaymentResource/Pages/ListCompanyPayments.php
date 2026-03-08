<?php

namespace App\Filament\FieldOperations\Resources\CompanyPaymentResource\Pages;

use App\Filament\FieldOperations\Resources\CompanyPaymentResource;
use App\Filament\FieldOperations\Resources\CompanyPaymentResource\Widgets\FinanceOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompanyPayments extends ListRecords
{
    protected static string $resource = CompanyPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            FinanceOverview::class,
        ];
    }
}