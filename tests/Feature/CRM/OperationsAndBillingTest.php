<?php

namespace Tests\Feature\CRM;

use App\Actions\Receivables\SettleReceivable;
use App\Actions\Services\ConvertQuoteToService;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Receivable;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OperationsAndBillingTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);

        return [$user, $tenant];
    }

    public function test_approved_quote_converts_only_once_and_copies_address(): void
    {
        [$user,$tenant] = $this->context();
        $client = Client::factory()->for($tenant)->create(['address' => 'Rua A', 'city' => 'BH']);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Approved, 'total_cents' => 12300]);
        $action = app(ConvertQuoteToService::class);
        $first = $action->execute($quote, [], $user->id);
        $second = $action->execute($quote, [], $user->id);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('Rua A', $first->address_snapshot_json['address']);
        $this->assertDatabaseCount('services', 1);
    }

    public function test_receivable_settlement_is_idempotent(): void
    {
        [$user,$tenant] = $this->context();
        $r = Receivable::factory()->for($tenant)->create(['amount_cents' => 5000]);
        $action = app(SettleReceivable::class);
        $action->execute($r, ['paid_amount_cents' => 5000], $user->id);
        $again = $action->execute($r, ['paid_amount_cents' => 4000], $user->id);
        $this->assertSame(5000, $again->paid_amount_cents);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_billing_limits_and_cancellation_preserve_subscription(): void
    {
        [$user,$tenant] = $this->context();
        $billing = app(BillingService::class);
        $subscription = $billing->start($tenant, 'basic');
        $this->assertSame(50, $billing->limits($tenant)['clients']['limit']);
        $billing->cancel($subscription);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'canceled']);
    }

    public function test_tenant_context_prevents_cross_tenant_receivable_settlement(): void
    {
        [$user,$tenant] = $this->context();
        $other = Tenant::factory()->create();
        $r = Receivable::factory()->for($other)->create();
        $this->expectException(ModelNotFoundException::class);
        app(SettleReceivable::class)->execute($r, [], $user->id);
    }
}
