<?php

namespace App\Filament\Resources\Users\UserResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

}
