<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Arr;

final class UpdateClient
{
    public function execute(Client $client, array $data): Client
    {
        $client->update(Arr::except($data, ['tenant_id']));

        return $client->refresh();
    }
}
