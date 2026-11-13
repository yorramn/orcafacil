<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TeamPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $this->isManager($user, $tenant);
    }

    public function invite(User $user, Tenant $tenant): bool
    {
        if (! $this->isManager($user, $tenant)) {
            return false;
        }

        $subscription = $tenant->subscriptions()->latest()->first();

        return $subscription?->plan === 'complete'
            && in_array($subscription->status->value, ['trialing', 'active'], true)
            && $tenant->users()->wherePivot('status', 'active')->count() < 5;
    }

    public function remove(User $user, Tenant $tenant, User $member): bool
    {
        return $member->getKey() !== $user->getKey()
            && $this->isManager($user, $tenant)
            && $tenant->users()->whereKey($member)->wherePivot('status', 'active')->exists();
    }

    private function isManager(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->whereKey($tenant)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->wherePivot('status', 'active')
            ->exists();
    }
}
