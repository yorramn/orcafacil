<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientsAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

        return [$user, $tenant];
    }

    public function test_client_creation_uses_context_and_ignores_request_tenant_id(): void
    {
        [$user, $tenant] = $this->authenticatedUser();
        $other = Tenant::factory()->create();

        $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String(), 'active_tenant_id' => $tenant->id])->post('/clients', [
            'tenant_id' => $other->id, 'name' => 'Cliente seguro', 'kind' => 'PF',
        ])->assertRedirect();

        $this->assertDatabaseHas('clients', ['name' => 'Cliente seguro', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('clients', ['name' => 'Cliente seguro', 'tenant_id' => $other->id]);
    }

    public function test_cross_tenant_client_update_is_denied(): void
    {
        [$user] = $this->authenticatedUser();
        $other = Tenant::factory()->create();
        $client = Client::factory()->for($other)->create();

        $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String()])->put('/clients/'.$client->id, [
            'name' => 'Tentativa', 'kind' => 'PF',
        ])->assertForbidden();
    }

    public function test_client_history_tabs_keep_the_selected_category_during_pagination(): void
    {
        [$user, $tenant] = $this->authenticatedUser();
        $client = Client::factory()->for($tenant)->create();
        Quote::factory()->count(11)->for($tenant)->for($client)->create();

        $this->actingAs($user)
            ->withSession(['two_factor_passed_at' => now()->toIso8601String(), 'active_tenant_id' => $tenant->id])
            ->get("/clients/{$client->id}?tab=quotes&quotes_page=2")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->where('tab', 'quotes')
                ->where('quotes.total', 11)
                ->has('quotes.data', 1)
                ->where('quotes.current_page', 2)
                ->where('quotes.prev_page_url', fn ($url) => str_contains($url, 'tab=quotes'))
                ->etc());

        $this->get("/clients/{$client->id}?tab=receivables")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->where('tab', 'receivables')
                ->where('receivables.total', 0)
                ->etc());
    }

    public function test_quote_detail_exposes_the_related_client_for_navigation(): void
    {
        [$user, $tenant] = $this->authenticatedUser();
        $client = Client::factory()->for($tenant)->create();
        $quote = Quote::factory()->for($tenant)->for($client)->create();

        $this->actingAs($user)
            ->withSession(['two_factor_passed_at' => now()->toIso8601String(), 'active_tenant_id' => $tenant->id])
            ->get("/quotes/{$quote->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Quotes/Show')
                ->where('quote.client.id', $client->id)
                ->etc());
    }

    public function test_cross_tenant_catalog_item_is_not_exposed(): void
    {
        [$user] = $this->authenticatedUser();
        $other = Tenant::factory()->create();
        $item = CatalogItem::factory()->for($other)->create();

        $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String()])->put('/catalog-items/'.$item->id, [
            'type' => 'service', 'name' => 'Tentativa', 'unit' => 'un', 'default_unit_price_cents' => 1,
        ])->assertForbidden();
    }
}
