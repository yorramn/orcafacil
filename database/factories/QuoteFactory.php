<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

final class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'client_id' => Client::factory(), 'created_by' => User::factory(), 'number' => $this->faker->unique()->numberBetween(1, 99999), 'title' => 'Orçamento', 'status' => 'draft', 'subtotal_cents' => 10000, 'total_cents' => 10000];
    }
}
