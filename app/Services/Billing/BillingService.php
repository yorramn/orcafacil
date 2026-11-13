<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\BillingWebhook;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingService
{
    public const PLANS = ['basic' => ['name' => 'Básico', 'price_cents' => 1500, 'limits' => ['users' => 1, 'clients' => 50, 'quotes' => 30, 'services' => 10]], 'complete' => ['name' => 'Completo', 'price_cents' => 3500, 'limits' => ['users' => 5, 'clients' => 500, 'quotes' => 300, 'services' => 100]]];

    public function __construct(private readonly BillingProvider $provider) {}

    public function verifyWebhook(string $payload, ?string $signature): bool
    {
        return $this->provider->verifyWebhook($payload, $signature);
    }

    public function start(Tenant $tenant, string $plan = 'basic'): Subscription
    {
        if (! isset(self::PLANS[$plan])) {
            throw ValidationException::withMessages(['plan' => 'Plano inválido.']);
        }

        return DB::transaction(function () use ($tenant, $plan) {
            $data = $this->provider->subscribe($tenant, $plan);

            return $tenant->subscriptions()->create(array_merge($data, ['plan' => $plan, 'status' => SubscriptionStatus::Trialing, 'provider' => 'fake', 'trial_ends_at' => now()->addDays(14), 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]));
        });
    }

    public function change(Subscription $subscription, string $plan): Subscription
    {
        if (! isset(self::PLANS[$plan])) {
            throw ValidationException::withMessages(['plan' => 'Plano inválido.']);
        } $subscription->update(array_merge($this->provider->change($subscription, $plan), ['plan' => $plan, 'status' => SubscriptionStatus::Active, 'canceled_at' => null]));

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $this->provider->cancel($subscription);
        $subscription->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()]);

        return $subscription->refresh();
    }

    public function processWebhook(array $payload): void
    {
        $eventId = (string) ($payload['id'] ?? '');
        if ($eventId === '') {
            throw ValidationException::withMessages(['webhook' => 'Evento sem identificador.']);
        }

        DB::transaction(function () use ($payload, $eventId): void {
            $webhook = BillingWebhook::query()->where('event_id', $eventId)->lockForUpdate()->first();
            if ($webhook?->processed_at !== null) {
                return;
            }
            $webhook ??= BillingWebhook::query()->create(['provider' => (string) ($payload['provider'] ?? 'fake'), 'event_id' => $eventId, 'event' => (string) ($payload['type'] ?? 'unknown'), 'payload_json' => $payload]);

            $data = (array) ($payload['data'] ?? $payload['subscription'] ?? []);
            $subscriptionId = $data['subscription_id'] ?? $data['subscriptionId'] ?? $data['local_subscription_id'] ?? null;
            $providerReference = $data['provider_reference'] ?? $data['providerReference'] ?? null;
            $query = Subscription::query()->lockForUpdate();
            if ($subscriptionId !== null) {
                $query->whereKey($subscriptionId);
            } elseif ($providerReference !== null) {
                $query->where('provider_reference', $providerReference);
            } else {
                return;
            }
            $subscription = $query->first();
            if ($subscription === null) {
                return;
            }
            $updates = array_filter(['plan' => $data['plan'] ?? null, 'status' => isset($data['status']) ? SubscriptionStatus::tryFrom((string) $data['status']) : null, 'provider_reference' => $providerReference, 'canceled_at' => ($data['status'] ?? null) === 'canceled' ? now() : null], static fn ($value): bool => $value !== null);
            if ($updates !== []) {
                $subscription->update($updates);
            }
            $webhook->update(['processed_at' => now()]);
        });
    }

    public function limits(Tenant $tenant): array
    {
        $s = $tenant->subscriptions()->latest()->first();
        $plan = $s?->plan ?? 'basic';
        $limits = self::PLANS[$plan]['limits'];

        return collect($limits)->mapWithKeys(fn ($limit, $key) => [$key => ['limit' => $limit, 'current' => $this->usage($tenant, $key)]])->all();
    }

    private function usage(Tenant $tenant, string $key): int
    {
        return match ($key) {
            'users' => $tenant->users()->wherePivot('status', 'active')->count(),
            'clients' => $tenant->clients()->whereNull('deleted_at')->count(),
            'quotes' => $tenant->quotes()->where('created_at', '>=', now()->startOfMonth())->count(),
            'services' => $tenant->services()->whereIn('status', ['planned', 'in_progress', 'paused'])->count(),
            default => 0,
        };
    }
}
