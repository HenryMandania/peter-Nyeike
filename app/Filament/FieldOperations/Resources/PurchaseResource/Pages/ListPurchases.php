<?php

namespace App\Filament\FieldOperations\Resources\PurchaseResource\Pages;

use App\Filament\FieldOperations\Resources\PurchaseResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

     protected function getDefaultTablePollingInterval(): ?string
    {
        return '5s';
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
            PurchaseResource\Widgets\PurchaseOverview::class,
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