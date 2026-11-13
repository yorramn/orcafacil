<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class InviteMember
{
    public function handle(Tenant $tenant, User $inviter, string $email, string $role = 'member'): Invitation
    {
        Gate::forUser($inviter)->authorize('team.invite', $tenant);
        $plain = Str::random(64);

        $email = strtolower(trim($email));
        $invitation = $tenant->invitations()->create(['invited_by' => $inviter->getKey(), 'email' => $email, 'role' => $role, 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDays(7)]);
        Notification::route('mail', $email)->notify((new TeamInvitation($tenant->name, $plain))->afterCommit());

        return $invitation;
    }
}
