<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'company_operator']);
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->hasRole('super_admin') || $user->company_id === $driver->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin']);
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->hasRole('super_admin') || ($user->hasRole('company_admin') && $user->company_id === $driver->company_id);
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $this->update($user, $driver);
    }
}