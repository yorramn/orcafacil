<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DashboardLayoutRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_dashboard_renders_using_the_selected_tenant_key(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('tenant.id', $tenant->id)
                ->where('tenant.name', $tenant->name)
                ->has('kpis', 4)
                ->has('quickActions', 2));
    }
}
