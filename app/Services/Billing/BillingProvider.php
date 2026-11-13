<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\Tenant;

interface BillingProvider
{
    public function subscribe(Tenant $tenant, string $plan): array;

    public function change(Subscription $subscription, string $plan): array;

    public function cancel(Subscription $subscription): void;

    public function verifyWebhook(string $payload, ?string $signature): bool;
}
