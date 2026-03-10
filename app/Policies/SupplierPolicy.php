<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Supplier;
use Illuminate\Support\Str;

class SupplierPolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(Supplier::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, Supplier $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, Supplier $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, Supplier $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
