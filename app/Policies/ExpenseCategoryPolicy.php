<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ExpenseCategory;
use Illuminate\Support\Str;

class ExpenseCategoryPolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(ExpenseCategory::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, ExpenseCategory $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, ExpenseCategory $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, ExpenseCategory $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
