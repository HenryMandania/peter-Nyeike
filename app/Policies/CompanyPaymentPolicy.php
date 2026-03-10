<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CompanyPayment;
use Illuminate\Support\Str;

class CompanyPaymentPolicy
{
    protected function getResourceName(): string
    {
        return 'companypayment';  
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, CompanyPayment $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, CompanyPayment $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, CompanyPayment $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
    
}
