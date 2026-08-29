<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'company_operator']);
    }

    public function view(User $user, Device $device): bool
    {
        return $user->hasRole('super_admin') || $user->company_id === $device->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin']);
    }

    public function update(User $user, Device $device): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('company_admin') && $user->company_id === $device->company_id;
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->hasRole('super_admin') || ($user->hasRole('company_admin') && $user->company_id === $device->company_id);
    }
}
