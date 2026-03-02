<?php

namespace App\Filament\FieldOperations\Resources\ExpenseCategoryResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\FieldOperations\Resources\ExpenseCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExpenseCategories extends ListRecords
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
