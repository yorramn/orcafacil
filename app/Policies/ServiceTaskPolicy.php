<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ServiceTask;
use App\Models\User;

final class ServiceTaskPolicy
{
    public function updateStatus(User $user, ServiceTask $task): bool
    {
        return $user->tenants()
            ->whereKey($task->tenant_id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->wherePivot('status', 'active')
            ->exists();
    }
}
