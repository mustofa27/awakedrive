<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin']);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->company_id === $model->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin']);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('company_admin') && $user->company_id === $model->company_id && $user->id !== $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('company_admin') && $user->company_id === $model->company_id && $user->id !== $model->id;
    }
}
