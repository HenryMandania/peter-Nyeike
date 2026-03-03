<?php

namespace App\Filament\FieldOperations\Resources\ExpenseResource\Pages;

use App\Filament\FieldOperations\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    protected static string $resource = ExpenseResource::class;

}
