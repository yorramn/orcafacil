<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class CreateClient
{
    public function __construct(private readonly TenantContext $context) {}

    public function execute(array $data): Client
    {
        $tenant = $this->context->get();
        if ($tenant->subscriptions()->whereIn('status', ['trialing', 'active'])->latest()->value('plan') === 'basic' && $tenant->clients()->where('status', 'active')->count() >= 50) {
            throw ValidationException::withMessages(['name' => 'Limite de 50 clientes ativos atingido.']);
        }

        return Client::query()->create([...Arr::except($data, ['tenant_id']), 'tenant_id' => $tenant->id]);
    }
}
