<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'client_id' => Client::factory(), 'title' => $this->faker->sentence(3), 'description' => $this->faker->paragraph(), 'status' => 'planned', 'contracted_total_cents' => $this->faker->numberBetween(1000, 100000), 'address_snapshot_json' => []];
    }
}
