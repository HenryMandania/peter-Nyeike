<?php

namespace App\Filament\Resources\Roles\RoleResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

}
