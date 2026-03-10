<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MpesaTransaction;
use Illuminate\Support\Str;

class MpesaTransactionPolicy
{
    protected function getResourceName(): string
    {        
        return 'mpesatransaction'; 
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, MpesaTransaction $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, MpesaTransaction $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, MpesaTransaction $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
