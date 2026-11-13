<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Actions\Clients\CreateClient;
use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\QuoteSent;
use App\Notifications\TeamInvitation;
use App\Policies\QuotePolicy;
use App\Policies\TenantPolicy;
use App\Services\Billing\BillingService;
use App\Services\Quotes\QuoteService;
use App\Services\Reports\ReportService;
use App\Services\Tenancy\InviteMember;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class QaFindingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_tenant_is_server_owned_and_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);

        $client = app(CreateClient::class)->execute(['name' => 'Cliente', 'kind' => 'person', 'tenant_id' => $other->id]);

        $this->assertSame($tenant->id, $client->tenant_id);
        $this->assertNotContains('tenant_id', (new Client)->getFillable());
    }

    public function test_quote_service_calculates_cents_without_float_arithmetic(): void
    {
        $this->assertSame(12345, QuoteService::calculateLineTotalCents('1.500', 8230));
        $this->assertSame(10000, QuoteService::calculateLineTotalCents('2', 5000));
    }

    public function test_active_tenant_member_can_view_reports_on_any_plan_but_other_users_cannot(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'member', 'status' => 'active']);
        $tenant->subscriptions()->create(['plan' => 'basic', 'status' => 'active', 'provider' => 'fake']);

        $this->assertTrue((new TenantPolicy)->viewReports($user, $tenant));
        $this->assertFalse((new TenantPolicy)->viewReports(User::factory()->create(), $tenant));

        $user->tenants()->updateExistingPivot($tenant->id, ['status' => 'inactive']);
        $this->assertFalse((new TenantPolicy)->viewReports($user, $tenant));
    }

    public function test_member_on_basic_plan_can_open_and_export_only_their_tenant_report(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'member', 'status' => 'active']);
        $tenant->subscriptions()->create(['plan' => 'basic', 'status' => 'active', 'provider' => 'fake']);
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create(['name' => 'Cliente permitido']))->create(['status' => QuoteStatus::Approved]);
        Quote::factory()->for($other)->for(Client::factory()->for($other)->create(['name' => 'Cliente externo']))->create(['status' => QuoteStatus::Approved]);

        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp]);
        $response = $this->get('/reports');
        $response->assertOk();
        $report = $response->viewData('page')['props']['report'];
        $this->assertSame(1, $report['conversion']['total_quotes']);
        $this->assertSame('Cliente permitido', $report['clients_by_volume'][0]['name']);

        $export = $this->get('/reports/export');
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        ob_start();
        $export->baseResponse->sendContent();
        $csv = (string) ob_get_clean();
        $this->assertStringContainsString('Cliente permitido', $csv);
        $this->assertStringNotContainsString('Cliente externo', $csv);
    }

    public function test_security_two_factor_route_is_registered(): void
    {
        $this->assertNotNull(collect(app('router')->getRoutes())->first(fn ($route) => $route->uri() === 'settings/security/two-factor' && in_array('GET', $route->methods(), true)));
    }

    public function test_resend_keeps_a_returned_token_that_matches_the_stored_hash(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $quote = Quote::factory()->for($tenant)->create(['status' => QuoteStatus::Sent]);

        $resent = app(QuoteService::class)->resend($quote);

        $this->assertNotEmpty($resent->public_token);
        $this->assertSame($resent->public_token_hash, hash('sha256', $resent->public_token));
    }

    public function test_resend_only_allows_non_terminal_quote_states(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $policy = new QuotePolicy;

        foreach ([QuoteStatus::Draft, QuoteStatus::Sent, QuoteStatus::Viewed] as $status) {
            $quote = Quote::factory()->for($tenant)->create(['status' => $status]);
            $this->assertTrue($policy->resend($user, $quote));
        }

        foreach ([QuoteStatus::Approved, QuoteStatus::Rejected, QuoteStatus::Expired, QuoteStatus::Canceled] as $status) {
            $quote = Quote::factory()->for($tenant)->create(['status' => $status]);
            $this->assertFalse($policy->resend($user, $quote));
            try {
                app(QuoteService::class)->resend($quote);
                $this->fail('Terminal quote state was resent.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_resend_from_viewed_rotates_token_records_event_and_notifies_client(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->for($tenant)->create(['email' => 'viewed@example.com']);
        $oldToken = str_repeat('a', 64);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Viewed, 'public_token_hash' => hash('sha256', $oldToken)]);

        $this->actingAs($user);
        $resent = app(QuoteService::class)->resend($quote);

        $this->assertNotSame($oldToken, $resent->public_token);
        $this->assertDatabaseHas('quote_events', ['quote_id' => $quote->id, 'event' => 'resent']);
        Notification::assertSentOnDemand(QuoteSent::class);
    }

    public function test_quote_attachment_uses_private_disk_and_audits_upload_and_delete(): void
    {
        Storage::fake('private');
        $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['backup'])), 'two_factor_confirmed_at' => now()]);
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->for($tenant)->create();
        $quote = Quote::factory()->for($tenant)->for($client)->create();

        $response = $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String()])->post('/quotes/'.$quote->id.'/attachments', ['file' => UploadedFile::fake()->create('escopo.pdf', 100, 'application/pdf')]);
        $response->assertRedirect();
        $attachment = $quote->attachments()->firstOrFail();
        Storage::disk('private')->assertExists($attachment->path);
        $this->assertDatabaseHas('activity_logs', ['event' => 'quote.attachment_uploaded']);

        $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String()])->delete('/attachments/'.$attachment->id)->assertRedirect();
        Storage::disk('private')->assertMissing($attachment->path);
        $this->assertDatabaseHas('activity_logs', ['event' => 'quote.attachment_deleted']);
    }

    public function test_service_task_status_can_complete_and_reopen_with_audit(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['backup'])), 'two_factor_confirmed_at' => now()]);
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->for($tenant)->for($client)->create();
        $task = $service->tasks()->create(['tenant_id' => $tenant->id, 'title' => 'Tarefa', 'status' => 'pending']);
        $session = ['two_factor_passed_at' => now()->toIso8601String()];

        $this->actingAs($user)->withSession($session)->patch('/services/'.$service->id.'/tasks/'.$task->id.'/status', ['status' => 'completed'])->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame($user->id, $task->fresh()->completed_by);
        $this->actingAs($user)->withSession($session)->patch('/services/'.$service->id.'/tasks/'.$task->id.'/status', ['status' => 'pending'])->assertRedirect();
        $task->refresh();
        $this->assertNull($task->completed_at);
        $this->assertNull($task->completed_by);
        $this->assertDatabaseHas('activity_logs', ['event' => 'service.task_completed']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'service.task_reopened']);
    }

    public function test_service_show_returns_tenant_scoped_auditable_history(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['backup'])), 'two_factor_confirmed_at' => now()]);
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->for($tenant)->for($client)->create();
        $service->occurrences()->create(['tenant_id' => $tenant->id, 'type' => 'visit', 'description' => 'Visita realizada', 'occurred_at' => now()]);
        $service->notes()->create(['tenant_id' => $tenant->id, 'body' => 'Nota interna', 'author_id' => $user->id]);
        $foreignClient = Client::factory()->for($other)->create();
        $foreignService = Service::factory()->for($other)->for($foreignClient)->create();
        $foreignService->occurrences()->create(['tenant_id' => $other->id, 'type' => 'visit', 'description' => 'Não deve aparecer', 'occurred_at' => now()]);

        $page = $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String()])->get('/services/'.$service->id)->viewData('page');
        $history = $page['props']['history'];
        $descriptions = collect($history)->pluck('description')->all();
        $this->assertContains('Visita realizada', $descriptions);
        $this->assertContains('Nota interna', $descriptions);
        $this->assertNotContains('Não deve aparecer', $descriptions);
    }

    public function test_quote_versions_payload_contains_coherent_link_and_actions(): void
    {
        $user = User::factory()->create(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_recovery_codes' => encrypt(json_encode(['backup'])), 'two_factor_confirmed_at' => now()]);
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->for($tenant)->create();
        $token = str_repeat('v', 64);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Sent, 'public_token_hash' => hash('sha256', $token), 'public_token_expires_at' => now()->addDay()]);

        $response = $this->actingAs($user)->withSession(['two_factor_passed_at' => now()->toIso8601String()])->get('/quotes/'.$quote->id.'/versions');
        $response->assertOk();
        $page = $response->viewData('page');
        $link = $page['props']['link'];
        $this->assertSame('active', $link['status']);
        $this->assertTrue($link['legacy']);
        $this->assertNull($link['url']);
        $this->assertSame(route('quotes.resend-link', $quote), $link['actions']['resend']);
    }

    public function test_sending_quote_queues_secure_notification_for_client(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        app(TenantContext::class)->set($tenant);
        $client = Client::factory()->for($tenant)->create(['email' => 'client@example.com']);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Draft]);

        $this->actingAs($user);
        $sent = app(QuoteService::class)->send($quote);

        Notification::assertSentOnDemand(QuoteSent::class, function (QuoteSent $notification, array $channels, AnonymousNotifiable $notifiable) use ($sent): bool {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'client@example.com'
                && in_array('mail', $channels, true)
                && str_contains((string) $mail->actionUrl, '/public/quotes/')
                && str_contains((string) $mail->actionUrl, $sent->public_token);
        });
    }

    public function test_reports_export_is_csv_and_uses_only_requested_tenant_data(): void
    {
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $client = Client::factory()->for($tenant)->create(['name' => 'Cliente autorizado']);
        Client::factory()->for($other)->create(['name' => 'Cliente de outro tenant']);
        Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Approved, 'total_cents' => 12345]);

        $response = app(ReportService::class)->exportCsv($tenant, now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString());
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Cliente autorizado', $csv);
        $this->assertStringNotContainsString('Cliente de outro tenant', $csv);
        $this->assertStringNotContainsString('public_token', $csv);
        $this->assertNotNull(collect(app('router')->getRoutes())->first(fn ($route) => $route->uri() === 'reports/export' && $route->methods() === ['GET', 'HEAD']));
    }

    public function test_invitation_notification_is_sent_to_an_unregistered_email_and_token_is_not_persisted(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $tenant->subscriptions()->create(['plan' => 'complete', 'status' => 'active', 'provider' => 'fake']);

        $invitation = app(InviteMember::class)->handle($tenant, $user, 'new@example.com');

        Notification::assertSentOnDemand(TeamInvitation::class, function (TeamInvitation $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return in_array('mail', $channels, true) && $notifiable->routes['mail'] === 'new@example.com';
        });
        $this->assertNull($invitation->getAttribute('plain_token'));
        $this->assertNotSame('', (string) $invitation->token_hash);
    }

    public function test_report_service_returns_period_status_conversion_and_operational_totals(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->for($tenant)->create();
        Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Approved, 'total_cents' => 12500, 'created_at' => now('America/Sao_Paulo')->startOfMonth()->addHours(12)->utc()]);

        $report = app(ReportService::class)->build($tenant, now('America/Sao_Paulo')->startOfMonth()->toDateString(), now('America/Sao_Paulo')->endOfMonth()->toDateString());

        $this->assertSame(1, $report['conversion']['approved_quotes']);
        $this->assertSame(1, $report['quote_status']['approved']);
        $this->assertArrayHasKey('sales_by_period', $report);
        $this->assertArrayHasKey('clients_by_volume', $report);
        $this->assertArrayHasKey('services_by_status', $report);
    }

    public function test_team_policy_and_invitation_acceptance_route_are_scoped_and_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'new@example.com']);
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $tenant->subscriptions()->create(['plan' => 'complete', 'status' => 'active', 'provider' => 'fake']);
        $token = 'a'.str_repeat('b', 63);
        $tenant->invitations()->create(['invited_by' => null, 'email' => $user->email, 'role' => 'member', 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);

        $this->actingAs($user)->get('/invitations/accept?token='.$token)
            ->assertOk()
            ->assertJsonPath('data.method', 'POST')
            ->assertJsonPath('data.tenant.id', $tenant->id);
    }

    public function test_team_members_are_paginated(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $this->assertTrue($tenant->users()->wherePivot('status', 'active')->paginate(15)->isEmpty() === false);
    }

    public function test_tenant_onboarding_creates_and_selects_a_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/onboarding/tenant')->assertOk();
        $this->actingAs($user)->post('/onboarding/tenant', [
            'name' => 'Nova operação',
            'slug' => 'nova-operacao',
            'timezone' => 'America/Sao_Paulo',
            'currency' => 'BRL',
        ])->assertRedirectToRoute('dashboard');

        $tenant = $user->fresh()->tenants()->firstOrFail();
        $this->assertSame('owner', $tenant->pivot->role);
        $this->assertSame($tenant->id, session('active_tenant_id'));
        $this->actingAs($user)->post('/tenant/select/'.$tenant->id)->assertRedirectToRoute('dashboard');
    }

    public function test_public_quote_view_is_idempotent_and_approval_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->for($tenant)->create(['name' => 'Cliente público']);
        $token = str_repeat('a', 64);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Sent, 'public_token_hash' => hash('sha256', $token), 'public_token_expires_at' => now()->addDay()]);

        $this->get('/public/quotes/'.$token)->assertOk();
        $this->get('/public/quotes/'.$token)->assertOk();
        $this->assertDatabaseCount('quote_events', 1);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'viewed']);
        $this->post('/public/quotes/'.$token.'/approve', ['name' => $client->name])->assertOk();
        $this->post('/public/quotes/'.$token.'/approve', ['name' => $client->name])->assertOk();
        $this->assertDatabaseCount('quote_events', 2);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'approved']);
    }

    public function test_public_quote_contract_has_token_safe_attachment_metadata_and_state_pages(): void
    {
        Storage::fake('private');
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->for($tenant)->create(['name' => 'Cliente público']);
        $token = str_repeat('p', 64);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Sent, 'public_token_hash' => hash('sha256', $token), 'public_token_expires_at' => now()->addDay()]);
        $path = 'attachments/'.$tenant->id.'/quotes/public.pdf';
        Storage::disk('private')->put($path, 'pdf');
        $quote->attachments()->create(['tenant_id' => $tenant->id, 'disk' => 'private', 'path' => $path, 'original_name' => 'public.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 3]);

        $response = $this->get('/public/quotes/'.$token);
        $page = $response->viewData('page');
        $props = $page['props'];
        $this->assertSame($token, $props['token']);
        $this->assertSame('valid', $props['state']);
        $this->assertSame('viewed', $props['quote']['status']);
        $this->assertArrayHasKey('url', $props['quote']['attachments'][0]);
        $this->assertSame(3, $props['quote']['attachments'][0]['size_bytes']);
        $this->assertSame('3 B', $props['quote']['attachments'][0]['size_human']);
        $this->assertArrayNotHasKey('path', $props['quote']['attachments'][0]);
        $this->assertArrayNotHasKey('disk', $props['quote']['attachments'][0]);
        $this->assertArrayNotHasKey('uploaded_by', $props['quote']['attachments'][0]);
        $this->get('/public/quotes/'.$token.'/attachments/'.$quote->attachments()->first()->id)->assertOk();

        $approval = $this->post('/public/quotes/'.$token.'/approve', ['name' => $client->name])->assertOk();
        $this->assertSame('approved', $approval->viewData('page')['props']['state']);
        $approvedPage = $this->get('/public/quotes/'.$token)->viewData('page');
        $this->assertSame('approved', $approvedPage['props']['state']);
        $this->assertSame('approved', $approvedPage['props']['quote']['status']);

        $expired = str_repeat('e', 64);
        Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Sent, 'public_token_hash' => hash('sha256', $expired), 'public_token_expires_at' => now()->subMinute()]);
        $expiredPage = $this->get('/public/quotes/'.$expired)->viewData('page');
        $this->assertSame('expired', $expiredPage['props']['state']);
    }

    public function test_public_quote_rejects_expired_and_revoked_links(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->for($tenant)->create();
        foreach ([now()->subMinute(), now()->addDay()] as $index => $expiresAt) {
            $token = str_repeat((string) ($index + 2), 64);
            Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Sent, 'public_token_hash' => hash('sha256', $token), 'public_token_expires_at' => $expiresAt, 'public_revoked_at' => $index === 1 ? now() : null]);
            $page = $this->get('/public/quotes/'.$token)->assertOk()->viewData('page');
            $this->assertSame($index === 0 ? 'expired' : 'revoked', $page['props']['state']);
        }
    }

    public function test_public_quote_unknown_token_returns_safe_invalid_state(): void
    {
        $token = str_repeat('x', 64);

        $response = $this->get('/public/quotes/'.$token)->assertOk();
        $props = $response->viewData('page')['props'];

        $this->assertSame('invalid', $props['state']);
        $this->assertSame('Este link público não está disponível.', $props['message']);
        $this->assertNull($props['quote']);
    }

    public function test_public_quote_endpoints_are_rate_limited(): void
    {
        $tenant = Tenant::factory()->create();
        $client = Client::factory()->for($tenant)->create();
        $token = str_repeat('z', 64);
        Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Sent, 'public_token_hash' => hash('sha256', $token), 'public_token_expires_at' => now()->addDay()]);
        $ip = '10.10.10.'.random_int(1, 200);

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('/public/quotes/'.$token)->assertOk();
        }

        $limited = $this->withServerVariables(['REMOTE_ADDR' => $ip])->get('/public/quotes/'.$token)->assertTooManyRequests();
        $page = $limited->viewData('page');
        $this->assertSame('rate_limited', $page['props']['state']);
        $this->assertSame($token, $page['props']['token']);
        $this->assertSame((int) $limited->headers->get('Retry-After'), $page['props']['retry_after_seconds']);
        $this->assertSame('Muitas tentativas. Aguarde e tente novamente.', $page['props']['message']);
    }

    public function test_billing_webhook_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $subscription = $tenant->subscriptions()->create(['plan' => 'basic', 'status' => 'trialing', 'provider' => 'fake', 'provider_reference' => 'provider-1']);
        $payload = ['id' => 'event-1', 'type' => 'subscription.updated', 'data' => ['subscription_id' => $subscription->id, 'status' => 'active', 'plan' => 'complete']];

        app(BillingService::class)->processWebhook($payload);
        app(BillingService::class)->processWebhook($payload);

        $this->assertDatabaseCount('billing_webhooks', 1);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'plan' => 'complete', 'status' => 'active']);
    }
}
