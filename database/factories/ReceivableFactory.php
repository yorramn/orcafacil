<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Receivable;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    public function definition(): array
    {
        return ['tenant_id' => Tenant::factory(), 'client_id' => Client::factory(), 'description' => 'Parcela', 'status' => 'pending', 'due_date' => now()->addDays(10), 'amount_cents' => 10000, 'paid_amount_cents' => 0, 'installment_number' => 1];
    }
}
