<?php

namespace App\Filament\FieldOperations\Resources\ExpenseCategoryResource\Pages;

use App\Filament\FieldOperations\Resources\ExpenseCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    protected static string $resource = ExpenseCategoryResource::class;
}
