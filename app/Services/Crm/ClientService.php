<?php

declare(strict_types=1);

namespace App\Services\Crm;

use App\Models\Client;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientService
{
    public function __construct(private readonly TenantContext $context) {}

    public function create(array $data): Client
    {
        $tenant = $this->context->get();
        if ($tenant->subscriptions()->whereIn('status', ['trialing', 'active'])->latest()->value('plan') === 'basic' && $tenant->users()->count() >= 1 && Client::forTenant($tenant->id())->where('status', 'active')->count() >= 50) {
            throw ValidationException::withMessages(['name' => 'Limite de 50 clientes ativos atingido.']);
        }

        return DB::transaction(fn () => Client::create(array_merge($data, ['tenant_id' => $tenant->id])));
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->refresh();
    }
}
