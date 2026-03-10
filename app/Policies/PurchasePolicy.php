<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Purchase;
use Illuminate\Support\Str;

class PurchasePolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(Purchase::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, Purchase $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, Purchase $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, Purchase $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }

    public function approve(User $user, Purchase $purchase): bool
    {    
        return $user->can('purchase.approve');
    }

    public function pay(User $user, Purchase $purchase): bool
    {
        return $user->can('purchase.pay');
    }
    public function sell(User $user, Purchase $purchase): bool
    {
        return $user->can('purchase.sell');
    }
}
