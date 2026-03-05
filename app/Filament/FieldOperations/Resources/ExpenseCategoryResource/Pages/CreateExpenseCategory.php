<?php

namespace App\Filament\FieldOperations\Resources\ExpenseCategoryResource\Pages;

use App\Filament\FieldOperations\Resources\ExpenseCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {    
        $data['created_by'] = auth()->id();

        return $data;
    }
}