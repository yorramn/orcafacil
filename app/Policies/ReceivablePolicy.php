<?php

namespace App\Policies;

use App\Models\Receivable;
use App\Models\User;

final class ReceivablePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->tenants()->wherePivot('status', 'active')->exists();
    }

    public function view(User $u, Receivable $r): bool
    {
        return $u->tenants()->whereKey($r->tenant_id)->wherePivot('status', 'active')->exists();
    }

    public function settle(User $u, Receivable $r): bool
    {
        return $this->view($u, $r) && $u->tenants()->whereKey($r->tenant_id)->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
    }

    public function cancel(User $u, Receivable $r): bool
    {
        return $this->settle($u, $r);
    }
}
