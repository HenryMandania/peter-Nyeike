<?php

namespace App\Policies;

use App\Models\User;
use App\Models\FloatRequest;

class FloatRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('floatrequest.view');
    }

    public function view(User $user, FloatRequest $model): bool
    {
        return $user->can('floatrequest.view');
    }

    public function create(User $user): bool
    {
        return $user->can('floatrequest.create');
    }

    public function update(User $user, FloatRequest $model): bool
    {
        return $user->can('floatrequest.edit');
    }

    public function delete(User $user, FloatRequest $model): bool
    {
        return $user->can('floatrequest.delete');
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