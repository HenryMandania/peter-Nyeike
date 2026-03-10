<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Sale;
use Illuminate\Support\Str;

class SalePolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(Sale::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, Sale $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, Sale $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, Sale $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
