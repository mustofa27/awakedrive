<?php

declare(strict_types=1);

use App\Models\Company;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('company.{companyId}.devices', function ($user, int $companyId) {
    if (! $user) {
        return false;
    }

    if ($user->hasRole('super_admin')) {
        return true;
    }

    return $user->company_id === $companyId;
});
