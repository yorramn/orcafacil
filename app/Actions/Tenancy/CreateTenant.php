<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateTenant
{
    /** @param array{name: string, slug: string, timezone: string, currency: string} $data */
    public function execute(User $user, array $data): Tenant
    {
        return DB::transaction(function () use ($user, $data): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'timezone' => $data['timezone'],
                'currency' => $data['currency'],
                'status' => 'active',
            ]);
            $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            $tenant->subscriptions()->create(['plan' => 'basic', 'status' => 'trialing', 'provider' => 'fake', 'trial_ends_at' => now()->addDays(14), 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

            return $tenant;
        });
    }
}
