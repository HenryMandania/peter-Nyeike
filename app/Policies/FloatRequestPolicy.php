<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FloatRequest;
use Illuminate\Support\Str;

class FloatRequestPolicy
{
    protected function getResourceName(): string
    {
        return Str::kebab(class_basename(FloatRequest::class));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function view(User $user, FloatRequest $model): bool
    {
        return $user->can($this->getResourceName() . '.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->getResourceName() . '.create');
    }

    public function update(User $user, FloatRequest $model): bool
    {
        return $user->can($this->getResourceName() . '.edit');
    }

    public function delete(User $user, FloatRequest $model): bool
    {
        return $user->can($this->getResourceName() . '.delete');
    } 

    public function approve(User $user, FloatRequest $floatRequest): bool
    {
        return $user->can('floatrequest.approve');
    }

    public function reject(User $user, FloatRequest $floatRequest): bool
    {
        return $user->can('floatrequest.reject');
    }
}
