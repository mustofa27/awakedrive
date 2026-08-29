<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'company_operator']);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin') || $user->company_id === $company->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin') || ($user->hasRole('company_admin') && $user->company_id === $company->id);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('super_admin');
    }
}
