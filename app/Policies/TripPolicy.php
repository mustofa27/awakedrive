<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool { return $user->hasRole(['super_admin', 'company_admin', 'company_operator']); }
    public function view(User $user, Trip $trip): bool { return $user->hasRole('super_admin') || $user->company_id === $trip->company_id; }
    public function create(User $user): bool { return $user->hasRole(['super_admin', 'company_admin']); }
    public function update(User $user, Trip $trip): bool { return $user->hasRole('super_admin') || ($user->hasRole('company_admin') && $user->company_id === $trip->company_id); }
    public function delete(User $user, Trip $trip): bool { return $this->update($user, $trip); }
}