<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Expense;
use Illuminate\Support\Str;

class ExpensePolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(Expense::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, Expense $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, Expense $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, Expense $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
