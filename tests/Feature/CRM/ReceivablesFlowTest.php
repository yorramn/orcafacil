<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Enums\QuoteStatus;
use App\Enums\ReceivableStatus;
use App\Enums\ServiceStatus;
use App\Jobs\MarkOverdueReceivables;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Receivable;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\ReportService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ReceivablesFlowTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $role = 'owner'): array
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => $role, 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp]);

        return [$user, $tenant];
    }

    private function service(Tenant $tenant, string $status = 'in_progress', int $total = 10000): Service
    {
        $client = Client::factory()->for($tenant)->create();
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Approved, 'total_cents' => $total, 'payment_terms' => 'Pagamento combinado com o cliente']);

        return Service::factory()->for($tenant)->for($client)->for($quote)->create(['status' => $status, 'contracted_total_cents' => $total]);
    }

    public function test_completion_and_installment_generation_are_atomic_and_idempotent(): void
    {
        [, $tenant] = $this->tenant();
        $service = $this->service($tenant);
        $url = "/services/{$service->id}/billing";
        $today = now('America/Sao_Paulo')->toDateString();

        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Services/Billing')->where('billingMode', 'complete')->where('billingExists', false)->where('service.quote.payment_terms', 'Pagamento combinado com o cliente'));
        $this->patch("/services/{$service->id}/status", ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post($url, ['installments' => [['description' => 'Metade', 'due_date' => $today, 'amount_cents' => 4000]]])->assertSessionHasErrors('installments');
        $this->assertSame(ServiceStatus::InProgress, $service->fresh()->status);
        $this->assertDatabaseCount('service_billings', 0);

        $installments = [
            ['description' => 'Entrada', 'due_date' => $today, 'amount_cents' => 4000],
            ['description' => 'Final', 'due_date' => now('America/Sao_Paulo')->addMonth()->toDateString(), 'amount_cents' => 6000],
        ];
        $this->post($url, ['installments' => $installments])->assertRedirect("/services/{$service->id}");
        $this->assertSame(ServiceStatus::Completed, $service->fresh()->status);
        $this->assertNotNull($service->fresh()->completed_at);
        $this->assertDatabaseHas('service_billings', ['service_id' => $service->id, 'total_cents' => 10000]);
        $this->assertSame([4000, 6000], $service->receivables()->orderBy('installment_number')->pluck('amount_cents')->all());
        $this->post($url, ['installments' => $installments])->assertRedirect();
        $this->assertDatabaseCount('service_billings', 1);
        $this->assertDatabaseCount('receivables', 2);
    }

    public function test_legacy_completed_service_can_be_billed_once_without_recreating_old_receivables(): void
    {
        [, $tenant] = $this->tenant();
        $legacy = $this->service($tenant, 'completed', 5000);
        $url = "/services/{$legacy->id}/billing";
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('Services/Billing')->where('billingMode', 'retroactive')->where('billingExists', false));
        $this->post($url, ['installments' => [['description' => 'Parcela única', 'due_date' => now('America/Sao_Paulo')->toDateString(), 'amount_cents' => 5000]]])->assertRedirect();
        $this->assertDatabaseCount('receivables', 1);

        $other = $this->service($tenant, 'completed', 3000);
        $other->receivables()->create(['tenant_id' => $tenant->id, 'client_id' => $other->client_id, 'description' => 'Legado', 'due_date' => today(), 'amount_cents' => 3000]);
        $this->get("/services/{$other->id}/billing")->assertInertia(fn (Assert $page) => $page->where('billingExists', true));
        $this->post("/services/{$other->id}/billing", ['installments' => [['description' => 'Duplicada', 'due_date' => now('America/Sao_Paulo')->toDateString(), 'amount_cents' => 3000]]])->assertSessionHasErrors('service');
        $this->assertDatabaseCount('receivables', 2);
    }

    public function test_zero_value_service_completes_without_receivable(): void
    {
        [, $tenant] = $this->tenant();
        $service = $this->service($tenant, 'in_progress', 0);
        $this->post("/services/{$service->id}/billing", ['installments' => []])->assertRedirect();
        $this->assertSame(ServiceStatus::Completed, $service->fresh()->status);
        $this->assertDatabaseCount('service_billings', 1);
        $this->assertDatabaseCount('receivables', 0);
    }

    public function test_only_eligible_services_in_the_active_tenant_can_be_billed(): void
    {
        [, $tenant] = $this->tenant();
        $installments = [['description' => 'Parcela única', 'due_date' => now('America/Sao_Paulo')->toDateString(), 'amount_cents' => 10000]];
        foreach (['planned', 'paused', 'canceled'] as $status) {
            $service = $this->service($tenant, $status);
            $this->post("/services/{$service->id}/billing", ['installments' => $installments])->assertSessionHasErrors('service');
            $this->assertSame(ServiceStatus::from($status), $service->fresh()->status);
        }
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->service($otherTenant);
        $this->get("/services/{$foreign->id}/billing")->assertForbidden();
        $this->post("/services/{$foreign->id}/billing", ['installments' => $installments])->assertForbidden();
        $this->assertDatabaseCount('service_billings', 0);
    }

    public function test_member_can_view_but_cannot_bill_settle_or_cancel(): void
    {
        [, $tenant] = $this->tenant('member');
        $service = $this->service($tenant);
        $receivable = Receivable::factory()->for($tenant)->for($service->client)->create(['service_id' => $service->id]);
        $this->get('/receivables')->assertOk();
        $this->get("/services/{$service->id}/billing")->assertForbidden();
        $this->post("/services/{$service->id}/billing", ['installments' => []])->assertForbidden();
        $this->post("/receivables/{$receivable->id}/payments")->assertForbidden();
        $this->post("/receivables/{$receivable->id}/cancel", ['reason' => 'Correção'])->assertForbidden();
    }

    public function test_full_payment_and_reasoned_cancel_keep_financial_history(): void
    {
        [, $tenant] = $this->tenant();
        $service = $this->service($tenant);
        $paid = Receivable::factory()->for($tenant)->for($service->client)->create(['service_id' => $service->id, 'amount_cents' => 9000]);
        $toCancel = Receivable::factory()->for($tenant)->for($service->client)->create(['service_id' => $service->id, 'amount_cents' => 4000]);

        $this->post("/receivables/{$paid->id}/payments", ['paid_amount_cents' => 100])->assertSessionHasErrors('paid_amount_cents');
        $this->post("/receivables/{$paid->id}/payments", ['payment_method' => 'PIX', 'paid_at' => now('America/Sao_Paulo')->toDateString()])->assertRedirect();
        $this->assertSame(9000, $paid->fresh()->paid_amount_cents);
        $this->assertSame(ReceivableStatus::Paid, $paid->fresh()->status);
        $this->post("/receivables/{$paid->id}/payments")->assertRedirect();
        $this->assertDatabaseCount('activity_logs', 1);
        $this->post("/receivables/{$paid->id}/cancel", ['reason' => 'Tentativa'])->assertSessionHasErrors('reason');
        $this->post("/receivables/{$toCancel->id}/cancel", [])->assertSessionHasErrors('reason');
        $this->post("/receivables/{$toCancel->id}/cancel", ['reason' => 'Cobrança dispensada'])->assertRedirect();
        $this->assertSame(ReceivableStatus::Canceled, $toCancel->fresh()->status);
        $this->assertSame('Cobrança dispensada', $toCancel->fresh()->cancellation_reason);
        $this->post("/receivables/{$toCancel->id}/payments")->assertStatus(422);
        $this->assertSame(ServiceStatus::InProgress, $service->fresh()->status);
    }

    public function test_listing_totals_csv_filters_and_report_payment_date_are_correct(): void
    {
        [, $tenant] = $this->tenant();
        $client = Client::factory()->for($tenant)->create(['name' => 'Cliente Alfa']);
        Receivable::factory()->for($tenant)->for($client)->create(['description' => 'Pendente Alfa', 'status' => 'pending', 'amount_cents' => 10000]);
        Receivable::factory()->for($tenant)->for($client)->create(['description' => 'Vencido Alfa', 'status' => 'overdue', 'amount_cents' => 5000]);
        Receivable::factory()->for($tenant)->for($client)->create(['description' => 'Pago Alfa', 'status' => 'paid', 'amount_cents' => 7000, 'paid_amount_cents' => 7000, 'paid_at' => now(), 'due_date' => now()->subMonths(2)]);
        Receivable::factory()->for($tenant)->for($client)->create(['description' => 'Cancelado Alfa', 'status' => 'canceled', 'amount_cents' => 2000]);

        $this->get('/receivables')->assertInertia(fn (Assert $page) => $page->component('Receivables/Index')->where('summary.pending', '150,00')->where('summary.overdue', '50,00')->where('summary.paid', '70,00'));
        $csv = $this->get('/receivables/export?status=overdue&search=Vencido');
        $csv->assertOk();
        ob_start();
        $csv->baseResponse->sendContent();
        $content = (string) ob_get_clean();
        $this->assertStringContainsString('Vencido Alfa', $content);
        $this->assertStringNotContainsString('Pendente Alfa', $content);

        $report = app(ReportService::class)->build($tenant, now('America/Sao_Paulo')->startOfMonth()->toDateString(), now('America/Sao_Paulo')->endOfMonth()->toDateString());
        $this->assertSame(7000, $report['receivables']['received_cents']);
    }

    public function test_overdue_job_uses_sao_paulo_today(): void
    {
        [, $tenant] = $this->tenant();
        $past = Receivable::factory()->for($tenant)->create(['due_date' => now('America/Sao_Paulo')->subDay()->toDateString()]);
        $today = Receivable::factory()->for($tenant)->create(['due_date' => now('America/Sao_Paulo')->toDateString()]);
        (new MarkOverdueReceivables)->handle();
        $this->assertSame(ReceivableStatus::Overdue, $past->fresh()->status);
        $this->assertSame(ReceivableStatus::Pending, $today->fresh()->status);
    }
}
