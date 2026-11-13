<?php

namespace App\Actions\Team;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptInvitation
{
    public function execute(string $token, User $user): void
    {
        DB::transaction(function () use ($token, $user) {
            $inv = Invitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $inv || $inv->accepted_at || $inv->expires_at->isPast() || strcasecmp($inv->email, $user->email) !== 0) {
                throw ValidationException::withMessages(['token' => 'Convite inválido ou expirado.']);
            }$user->tenants()->syncWithoutDetaching([$inv->tenant_id => ['role' => $inv->role, 'status' => 'active', 'joined_at' => now()]]);
            $inv->update(['accepted_at' => now()]);
        });
    }
}
