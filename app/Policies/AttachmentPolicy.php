<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;

final class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        return ($parent instanceof Quote || $parent instanceof Service)
            && $user->can('view', $parent)
            && $user->tenants()->whereKey($attachment->tenant_id)->wherePivot('status', 'active')->exists();
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;

        if ($parent instanceof Quote || $parent instanceof Service) {
            return $user->can($parent instanceof Quote ? 'view' : 'update', $parent)
                && $user->tenants()->whereKey($attachment->tenant_id)->wherePivot('status', 'active')->exists();
        }

        return false;
    }
}
