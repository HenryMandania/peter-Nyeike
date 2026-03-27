<?php

namespace App\Filament\FieldOperations\Resources\ExpenseResource\Pages;

use App\Filament\FieldOperations\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

  
    protected function getDefaultTablePollingInterval(): ?string
    {
        return '10s';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\FieldOperations\Resources\ExpenseResource\Widgets\ExpenseOverview::class,
        ];
    }

     
    protected function getTableQuery(): ?Builder
    {
        $user = Auth::user();
        $query = parent::getTableQuery();

       
        if ($user->hasAnyRole(['admin', 'supervisor'])) {
            return $query;
        }

        return $query->whereHas('shift', function (Builder $query) use ($user) {
            $query->where('user_id', $user->id);
        });
    }
}