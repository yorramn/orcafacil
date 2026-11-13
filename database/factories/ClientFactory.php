<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'name' => fake()->name(), 'kind' => 'person', 'email' => fake()->safeEmail(), 'status' => 'active'];
    }
}
