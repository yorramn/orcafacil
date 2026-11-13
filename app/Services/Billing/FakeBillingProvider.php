<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Str;

final class FakeBillingProvider implements BillingProvider
{
    public function subscribe(Tenant $tenant, string $plan): array
    {
        return ['provider_reference' => 'fake_'.Str::lower(Str::random(20)), 'provider_customer_reference' => 'cus_'.$tenant->id];
    }

    public function change(Subscription $subscription, string $plan): array
    {
        return ['provider_reference' => $subscription->provider_reference ?? 'fake_'.Str::random(20)];
    }

    public function cancel(Subscription $subscription): void {}

    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        $secret = (string) config('services.billing.webhook_secret', '');

        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
