<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CrudNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): Tenant
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp]);

        return $tenant;
    }

    public function test_catalog_crud_returns_to_the_filtered_grid_with_a_flash_message(): void
    {
        $tenant = $this->authenticate();
        $data = ['type' => 'service', 'name' => 'Instalação', 'unit' => 'un', 'default_unit_price_cents' => 15000, 'active' => true];
        $created = $this->post('/catalog-items', $data);
        $item = CatalogItem::query()->forTenant($tenant->id)->firstOrFail();
        $created->assertRedirect(route('catalog.index', ['focus' => $item->id, 'search' => $item->name, 'status' => 'active']))
            ->assertSessionHas('success', 'Item do catálogo criado com sucesso!');
        $this->get($created->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')->where('flash.success', 'Item do catálogo criado com sucesso!')
            ->has('items.data', 1)->where('items.data.0.id', $item->id));

        $edited = $this->patch('/catalog/'.$item->id, [...$data, 'name' => 'Instalação revisada']);
        $edited->assertRedirect(route('catalog.index', ['focus' => $item->id, 'search' => 'Instalação revisada', 'status' => 'active']))
            ->assertSessionHas('success', 'Item do catálogo editado com sucesso!');

        $deleted = $this->delete('/catalog-items/'.$item->id);
        $deleted->assertRedirect(route('catalog.index', ['focus' => $item->id, 'search' => 'Instalação revisada', 'status' => 'inactive']))
            ->assertSessionHas('success', 'Item do catálogo excluído com sucesso!');
        $this->get($deleted->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')->has('items.data', 1)->where('items.data.0.active', false));
    }

    public function test_catalog_detail_is_available_only_for_the_active_tenant(): void
    {
        $tenant = $this->authenticate();
        $item = CatalogItem::factory()->for($tenant)->create(['name' => 'Instalação de varal']);
        $otherItem = CatalogItem::factory()->for(Tenant::factory()->create())->create();

        $this->get('/catalog/'.$item->id)->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Show')->where('item.id', $item->id)->where('item.name', 'Instalação de varal'));
        $this->get('/catalog/'.$otherItem->id)->assertNotFound();
    }

    public function test_member_can_view_records_without_management_actions(): void
    {
        $tenant = $this->authenticate();
        $item = CatalogItem::factory()->for($tenant)->create();
        $client = Client::factory()->for($tenant)->create();
        $member = User::factory()->create();
        $member->tenants()->attach($tenant, ['role' => 'member', 'status' => 'active']);
        $member->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp]);

        $this->get('/catalog')->assertInertia(fn (Assert $page) => $page
            ->component('Catalog/Index')->where('canManage', false));
        $this->get('/clients')->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')->where('canManage', false));
        $this->get('/catalog/'.$item->id)->assertOk();
        $this->get('/clients/'.$client->id)->assertOk();
    }

    public function test_client_crud_returns_to_the_filtered_grid_including_archived_records(): void
    {
        $tenant = $this->authenticate();
        $created = $this->post('/clients', ['name' => 'Cliente inicial', 'kind' => 'person']);
        $client = Client::query()->forTenant($tenant->id)->firstOrFail();
        $created->assertRedirect(route('clients.index', ['focus' => $client->id, 'search' => 'Cliente inicial', 'status' => 'active']))
            ->assertSessionHas('success', 'Cliente criado com sucesso!');

        $edited = $this->patch('/clients/'.$client->id, ['name' => 'Cliente revisado', 'kind' => 'person']);
        $edited->assertRedirect(route('clients.index', ['focus' => $client->id, 'search' => 'Cliente revisado']))
            ->assertSessionHas('success', 'Cliente editado com sucesso!');

        $deleted = $this->delete('/clients/'.$client->id);
        $deleted->assertRedirect(route('clients.index', ['focus' => $client->id, 'search' => 'Cliente revisado', 'status' => 'archived']))
            ->assertSessionHas('success', 'Cliente excluído com sucesso!');
        $this->get($deleted->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')->has('clients.data', 1)->where('clients.data.0.id', $client->id));
    }

    public function test_quote_and_service_creation_return_to_their_filtered_grids(): void
    {
        $tenant = $this->authenticate();
        $client = Client::factory()->for($tenant)->create();
        $created = $this->post('/quotes', [
            'client_id' => $client->id,
            'title' => 'Proposta de instalação',
            'items' => [['description' => 'Instalação', 'unit' => 'un', 'quantity' => '1', 'unit_price_cents' => 15000]],
        ]);
        $quote = Quote::query()->forTenant($tenant->id)->firstOrFail();
        $created->assertRedirect(route('quotes.index', ['focus' => $quote->id, 'search' => $quote->display_number]))
            ->assertSessionHas('success', 'Orçamento criado com sucesso!');
        $this->get($created->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Index')->has('quotes.data', 1)->where('quotes.data.0.id', $quote->id));

        $quote->update(['status' => 'approved']);
        $serviceCreated = $this->post('/services', ['quote_id' => $quote->id, 'title' => 'Execução da instalação']);
        $service = Service::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $serviceCreated->assertRedirect(route('services.index', ['focus' => $service->id, 'search' => $service->title]))
            ->assertSessionHas('success', 'Serviço criado com sucesso!');
        $this->get($serviceCreated->headers->get('Location'))->assertInertia(fn (Assert $page) => $page
            ->component('Services/Index')->has('services.data', 1)->where('services.data.0.id', $service->id));
    }
}
