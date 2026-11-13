<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use LogicException;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function resolveFor(User $user, ?int $tenantId = null): Tenant
    {
        $query = $user->tenants()->wherePivot('status', 'active');
        $tenant = $tenantId === null ? $query->first() : $query->whereKey($tenantId)->first();

        if ($tenant === null) {
            throw new LogicException('The authenticated user has no active tenant association.');
        }

        $this->tenant = $tenant;

        return $tenant;
    }

    public function get(): Tenant
    {
        return $this->tenant ?? throw new LogicException('Tenant context has not been resolved.');
    }

    public function id(): int
    {
        return $this->get()->getKey();
    }
}
