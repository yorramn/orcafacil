<?php

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CatalogItem> */
final class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'type' => 'service', 'name' => fake()->words(3, true), 'unit' => 'un', 'default_unit_price_cents' => 10000, 'active' => true];
    }
}
