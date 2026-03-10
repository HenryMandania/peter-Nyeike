<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Item;
use Illuminate\Support\Str;

class ItemPolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(Item::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, Item $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, Item $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, Item $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
