<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DeviceAlert;
use App\Models\User;

class DeviceAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'company_operator']);
    }

    public function view(User $user, DeviceAlert $alert): bool
    {
        return $user->hasRole('super_admin') || $user->company_id === $alert->device->company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'company_operator']);
    }

    public function update(User $user, DeviceAlert $alert): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'company_operator'])
            && ($user->hasRole('super_admin') || $user->company_id === $alert->device->company_id);
    }

    public function delete(User $user, DeviceAlert $alert): bool
    {
        return $user->hasRole('super_admin') || ($user->hasRole('company_admin') && $user->company_id === $alert->device->company_id);
    }
}
