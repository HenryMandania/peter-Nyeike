<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Str;

class CompanyPolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(Company::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, Company $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, Company $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, Company $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    }
}
