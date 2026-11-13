<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

final class ServicePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }

    public function view(User $u, Service $s): bool
    {
        return $this->same($u, $s);
    }

    public function create(User $u): bool
    {
        return $u->tenants()->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }

    public function update(User $u, Service $s): bool
    {
        return $this->same($u, $s) && $this->manager($u, $s->tenant_id);
    }

    private function same(User $u, Service $s): bool
    {
        return $u->tenants()->whereKey($s->tenant_id)->wherePivot('status', 'active')->exists();
    }

    private function manager(User $u, int $tenantId): bool
    {
        return $u->tenants()->whereKey($tenantId)->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }
}
