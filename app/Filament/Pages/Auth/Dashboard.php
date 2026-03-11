<?php

namespace App\Filament\FieldOperations\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        // Only allow if user has the specific permission
        return auth()->user()->can('dashboard.view');
    }

    // Optional: Redirect to the desired page if access is denied
    public function mount()
    {
        if (!auth()->user()->can('dashboard.view')) {
            return redirect('/field-operations/shifts');
        }
    }
}