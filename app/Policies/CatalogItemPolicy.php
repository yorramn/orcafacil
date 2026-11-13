<?php

namespace App\Policies;

use App\Models\CatalogItem;
use App\Models\User;

final class CatalogItemPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }

    public function view(User $u, CatalogItem $i): bool
    {
        return $this->same($u, $i);
    }

    public function create(User $u): bool
    {
        return $u->tenants()->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }

    public function update(User $u, CatalogItem $i): bool
    {
        return $this->same($u, $i) && $this->manager($u, $i->tenant_id);
    }

    public function delete(User $u, CatalogItem $i): bool
    {
        return $this->update($u, $i);
    }

    private function same(User $u, CatalogItem $i): bool
    {
        return $u->tenants()->whereKey($i->tenant_id)->wherePivot('status', 'active')->exists();
    }

    private function manager(User $u, int $tenantId): bool
    {
        return $u->tenants()->whereKey($tenantId)->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }
}
