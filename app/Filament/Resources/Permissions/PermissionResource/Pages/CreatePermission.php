<?php

namespace App\Filament\Resources\Permissions\PermissionResource\Pages;

use App\Filament\Resources\Permissions\PermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePermission extends CreateRecord
{
    protected static string $resource = PermissionResource::class;
}
