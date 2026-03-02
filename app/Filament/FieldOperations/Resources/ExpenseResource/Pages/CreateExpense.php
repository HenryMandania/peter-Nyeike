<?php

namespace App\Filament\FieldOperations\Resources\ExpenseResource\Pages;

use App\Filament\FieldOperations\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;
}
