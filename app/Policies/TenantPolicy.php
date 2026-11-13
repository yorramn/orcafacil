<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    public function create(User $user): bool
    {
        return ! $user->tenants()->wherePivot('status', 'active')->exists();
    }

    public function select(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant);
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->whereKey($tenant->getKey())->wherePivot('status', 'active')->exists();
    }

    public function manage(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->whereKey($tenant->getKey())->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }

    public function manageBilling(User $user, Tenant $tenant): bool
    {
        return $this->manage($user, $tenant);
    }

    public function viewReports(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant);
    }

    public function viewActivity(User $user, Tenant $tenant): bool
    {
        return $this->view($user, $tenant);
    }
}
