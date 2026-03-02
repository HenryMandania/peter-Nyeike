<?php

namespace App\Filament\FieldOperations\Resources\FloatRequestResource\Pages;

use App\Models\Shift;
use App\Filament\FieldOperations\Resources\FloatRequestResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateFloatRequest extends CreateRecord
{
    protected static string $resource = FloatRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';
    
        // Link this float request to the CURRENT OPEN SHIFT if it exists
        $activeShift = Shift::where('user_id', Auth::id())
            ->where('status', 'open')
            ->first();
    
        $data['shift_id'] = $activeShift?->id;
    
        return $data;
    }
}