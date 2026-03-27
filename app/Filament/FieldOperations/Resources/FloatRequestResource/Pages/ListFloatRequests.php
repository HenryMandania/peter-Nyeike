<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;

use App\Filament\FieldOperations\Resources\FloatRequestResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListFloatRequests extends ListRecords
{
    protected static string $resource = FloatRequestResource::class;

    /**
     * Optional: Add polling if you want operators to see 
     * their approval status updates in real-time.
     */
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
            FloatRequestResource\Widgets\FloatRequestOverview::class,
        ];
    }

    /**
     * Restricts the visibility of float requests.
     * Non-admins only see requests they created.
     */
    protected function getTableQuery(): ?Builder
    {
        $user = Auth::user();
        $query = parent::getTableQuery();

        // Allow management to see all requests for oversight/approval
        if ($user->hasAnyRole(['admin', 'supervisor'])) {
            return $query;
        }

        // Standard users only see their own requests
        // Assumes your float_requests table has a 'user_id' or 'created_by' column
        return $query->where('user_id', $user->id);
    }
}