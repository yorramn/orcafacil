<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }

    public function view(User $u, Client $c): bool
    {
        return $this->same($u, $c) && $this->member($u);
    }

    public function create(User $u): bool
    {
        return $this->member($u);
    }

    public function update(User $u, Client $c): bool
    {
        return $this->same($u, $c) && $this->manager($u, $c->tenant_id);
    }

    public function delete(User $u, Client $c): bool
    {
        return $this->same($u, $c) && $this->manager($u, $c->tenant_id);
    }

    private function same(User $u, Client $c): bool
    {
        return $u->tenants()->whereKey($c->tenant_id)->wherePivot('status', 'active')->exists();
    }

    private function member(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }

    private function manager(User $u, int $tenantId): bool
    {
        return $u->tenants()->whereKey($tenantId)->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }
}
