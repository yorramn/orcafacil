<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Enums\QuoteStatus;
use App\Enums\ServiceStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\QuoteSent;
use App\Services\Tenancy\TenantContext;
use App\Services\Quotes\QuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class QuoteDistributionTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        app(TenantContext::class)->set($tenant);
        $this->actingAs($user)->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp]);

        return $tenant;
    }

    public function test_service_status_actions_and_edit_options_follow_the_transition_rules(): void
    {
        $tenant = $this->tenant();
        $service = Service::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['status' => ServiceStatus::Planned]);
        $url = "/services/{$service->id}/status";

        $this->get("/services/{$service->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Edit')->where('availableStatuses', ['in_progress', 'canceled']));
        $this->patch($url, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame(ServiceStatus::Planned, $service->fresh()->status);

        $this->patch($url, ['status' => 'in_progress'])->assertRedirect();
        $this->get("/services/{$service->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Edit')->where('availableStatuses', ['paused', 'completed', 'canceled']));
        foreach (['in_progress', 'planned'] as $invalid) {
            $this->patch($url, ['status' => $invalid])->assertSessionHasErrors('status');
            $this->assertSame(ServiceStatus::InProgress, $service->fresh()->status);
        }

        $this->patch($url, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post("/services/{$service->id}/billing", ['installments' => [[
            'description' => 'Parcela única',
            'due_date' => now('America/Sao_Paulo')->toDateString(),
            'amount_cents' => $service->contracted_total_cents,
        ]]])->assertRedirect();
        $this->get("/services/{$service->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Edit')->where('availableStatuses', []));
        $this->patch($url, ['status' => 'canceled'])->assertSessionHasErrors('status');
        $this->assertSame(ServiceStatus::Completed, $service->fresh()->status);

        $canceled = Service::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['status' => ServiceStatus::Canceled]);
        $this->patch("/services/{$canceled->id}/status", ['status' => 'in_progress'])->assertSessionHasErrors('status');
        $this->assertSame(ServiceStatus::Canceled, $canceled->fresh()->status);
    }

    public function test_quote_without_client_email_cannot_be_sent_and_can_be_downloaded_as_pdf(): void
    {
        $tenant = $this->tenant();
        $client = Client::factory()->for($tenant)->create(['email' => null]);
        $quote = Quote::factory()->for($tenant)->for($client)->create(['status' => QuoteStatus::Draft]);

        $this->post("/quotes/{$quote->id}/send")->assertSessionHasErrors('client_id');
        $this->assertSame(QuoteStatus::Draft, $quote->refresh()->status);

        $quote->update(['status' => QuoteStatus::Sent]);
        $this->post("/quotes/{$quote->id}/resend-link")->assertSessionHasErrors('client_id');

        $pdf = $this->get("/quotes/{$quote->id}/download");
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_pdf_and_public_link_use_sao_paulo_date_and_pdf_shows_client_contacts_without_attachment_names(): void
    {
        $tenant = $this->tenant();
        $client = Client::factory()->for($tenant)->create([
            'phone' => '(11) 99999-1234',
            'email' => 'cliente@example.com',
        ]);
        $quote = Quote::factory()->for($tenant)->for($client)->create([
            'created_at' => '2026-11-05 01:30:00',
            'valid_until' => '2026-11-10',
        ]);
        $quote->attachments()->create([
            'tenant_id' => $tenant->id,
            'disk' => 'private',
            'path' => 'quotes/anexo.pdf',
            'original_name' => 'anexo-inacessivel.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);

        $html = view('quotes.pdf', ['quote' => $quote->load(['client', 'items', 'tenant', 'attachments'])])->render();
        $this->assertStringContainsString('04/11/2026', $html);
        $this->assertStringContainsString('10/11/2026', $html);
        $this->assertStringContainsString('(11) 99999-1234', $html);
        $this->assertStringContainsString('cliente@example.com', $html);
        $this->assertStringNotContainsString('anexo-inacessivel.pdf', $html);

        $this->post("/quotes/{$quote->id}/public-link")->assertRedirect();
        $page = $this->get($quote->refresh()->publicUrl())->viewData('page');
        $this->assertSame('2026-11-05T01:30:00+00:00', $page['props']['quote']['created_at']);
        $this->assertSame('2026-11-10', $page['props']['quote']['valid_until']);
    }

    public function test_public_link_remains_copyable_after_a_later_page_visit(): void
    {
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create(['email' => null]))->create();

        $this->post("/quotes/{$quote->id}/public-link")->assertRedirect();
        $url = $quote->refresh()->publicUrl();
        $this->assertNotNull($url);
        $this->assertSame($url, $quote->fresh()->publicUrl());
        $this->assertNotEmpty($quote->public_token_hash);
        $this->get("/quotes/{$quote->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Show')->where('publicUrl', $url));
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_video_can_be_attached_after_quote_is_sent(): void
    {
        Storage::fake('private');
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['status' => QuoteStatus::Sent]);

        $this->post("/quotes/{$quote->id}/attachments", ['file' => UploadedFile::fake()->create('apresentacao.mp4', 500, 'video/mp4')])->assertRedirect();
        $attachment = $quote->attachments()->firstOrFail();
        $this->assertSame('video/mp4', $attachment->mime_type);
        Storage::disk('private')->assertExists($attachment->path);
        $this->get("/attachments/{$attachment->id}/view")->assertOk()->assertHeader('content-type', 'video/mp4');
        $this->post("/quotes/{$quote->id}/public-link")->assertRedirect();
        $token = $quote->refresh()->public_token_encrypted;
        $this->get("/public/quotes/{$token}/attachments/{$attachment->id}")->assertOk()->assertHeader('content-type', 'video/mp4');
    }

    public function test_quote_creation_accepts_media_files(): void
    {
        Storage::fake('private');
        $tenant = $this->tenant();
        $client = Client::factory()->for($tenant)->create(['email' => null]);

        $this->post('/quotes', [
            'client_id' => $client->id,
            'title' => 'Projeto com vídeo',
            'items' => [['description' => 'Serviço', 'unit' => 'un', 'quantity' => '1', 'unit_price_cents' => 10000]],
            'media' => [UploadedFile::fake()->create('apresentacao.mp4', 500, 'video/mp4')],
        ])->assertRedirect();

        $quote = Quote::query()->forTenant($tenant->id)->firstOrFail();
        $this->assertCount(1, $quote->attachments);
        Storage::disk('private')->assertExists($quote->attachments->first()->path);
    }

    public function test_resending_email_preserves_a_previously_shared_active_link(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create(['email' => 'cliente@example.com']))->create(['status' => QuoteStatus::Draft]);

        $this->post("/quotes/{$quote->id}/public-link")->assertRedirect();
        $url = $quote->refresh()->publicUrl();
        $this->post("/quotes/{$quote->id}/send")->assertRedirect();
        $this->post("/quotes/{$quote->id}/resend-link")->assertRedirect();

        Notification::assertSentOnDemandTimes(QuoteSent::class, 2);
        $this->assertSame($url, $quote->refresh()->publicUrl());
        $this->get($url)->assertOk();
    }

    public function test_resending_a_draft_creates_the_first_sent_version(): void
    {
        Notification::fake();
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create(['email' => 'cliente@example.com']))->create(['status' => QuoteStatus::Draft]);

        $this->post("/quotes/{$quote->id}/resend-link")->assertRedirect();

        $this->assertSame(QuoteStatus::Sent, $quote->refresh()->status);
        $this->assertCount(1, $quote->versions);
        $this->assertSame('sent', $quote->events()->firstOrFail()->event);
    }

    public function test_smtp_failure_keeps_quote_as_draft_and_returns_a_form_error(): void
    {
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create(['email' => 'cliente@example.com']))->create(['status' => QuoteStatus::Draft]);
        Mail::shouldReceive('mailer')->once()
            ->andThrow(new \RuntimeException('SMTP indisponível'));

        $this->post("/quotes/{$quote->id}/send")->assertSessionHasErrors('email');

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
        $this->assertNull($quote->fresh()->sent_at);
        $this->assertSame(0, $quote->versions()->count());
        $this->assertSame(0, $quote->events()->count());
    }

    public function test_expired_quote_cannot_generate_a_new_public_link(): void
    {
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['valid_until' => now()->subDay()]);

        $this->post("/quotes/{$quote->id}/public-link")->assertSessionHasErrors('valid_until');
        $this->assertNull($quote->refresh()->public_token_hash);
    }

    public function test_editing_discount_recalculates_the_total_used_by_the_pdf(): void
    {
        $tenant = $this->tenant();
        $client = Client::factory()->for($tenant)->create();
        $quote = Quote::factory()->for($tenant)->for($client)->create(['discount_cents' => 0]);

        app(QuoteService::class)->update($quote, [
            'client_id' => $client->id,
            'title' => 'Orçamento revisado',
            'discount_cents' => 1000,
            'extra_cents' => 0,
            'items' => [['description' => 'Serviço', 'unit' => 'un', 'quantity' => '1', 'unit_price_cents' => 10000]],
        ]);

        $this->assertSame(9000, (int) $quote->refresh()->total_cents);
    }

    public function test_quote_number_is_formatted_consistently_in_listing_and_public_view(): void
    {
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create([
            'number' => 154,
            'created_at' => '2026-11-05 12:00:00',
        ]);

        $this->assertSame('0154-26', $quote->display_number);
        $this->get('/quotes?search=0154-26')->assertInertia(fn (Assert $page) => $page
            ->component('Quotes/Index')->has('quotes.data', 1)
            ->where('quotes.data.0.display_number', '0154-26')
            ->where('quotes.data.0.client.name', $quote->client->name));

        $this->post("/quotes/{$quote->id}/public-link")->assertRedirect();
        $this->get($quote->refresh()->publicUrl())->assertInertia(fn (Assert $page) => $page
            ->component('Public/Quote')->where('quote.number', '0154-26'));
    }

    public function test_approved_quote_prefills_service_creation_and_other_tenants_cannot_select_it(): void
    {
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create([
            'status' => QuoteStatus::Approved,
            'title' => 'Instalação elétrica',
            'description' => 'Instalar quadros e luminárias',
        ]);

        $this->get("/services/create?quote={$quote->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Create')
            ->where('selectedQuoteId', $quote->id)
            ->where('quotes.0.title', 'Instalação elétrica')
            ->where('quotes.0.description', 'Instalar quadros e luminárias'));

        $draft = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['status' => QuoteStatus::Draft]);
        $this->get("/services/create?quote={$draft->id}")->assertNotFound();

        $otherTenant = Tenant::factory()->create();
        $otherQuote = Quote::factory()->for($otherTenant)->for(Client::factory()->for($otherTenant)->create())->create(['status' => QuoteStatus::Approved]);
        $this->get("/services/create?quote={$otherQuote->id}")->assertNotFound();
    }

    public function test_service_dates_require_a_start_within_90_days_and_an_end_within_six_months(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC'));
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['status' => QuoteStatus::Approved]);
        $today = CarbonImmutable::today('America/Sao_Paulo');
        $payload = ['quote_id' => $quote->id, 'title' => 'Serviço agendado'];

        $this->post('/services', $payload + ['scheduled_start' => $today->subDay()->toDateString()])->assertSessionHasErrors('scheduled_start');
        $this->post('/services', $payload + ['scheduled_start' => $today->addDays(91)->toDateString()])->assertSessionHasErrors('scheduled_start');
        $this->post('/services', $payload + ['scheduled_end' => $today->toDateString()])->assertSessionHasErrors('scheduled_start');

        $start = $today->addDays(90);
        $this->post('/services', $payload + ['scheduled_start' => $start->toDateString(), 'scheduled_end' => $start->subDay()->toDateString()])->assertSessionHasErrors('scheduled_end');
        $this->post('/services', $payload + ['scheduled_start' => $start->toDateString(), 'scheduled_end' => $start->addMonthsNoOverflow(6)->addDay()->toDateString()])->assertSessionHasErrors('scheduled_end');
        $this->assertDatabaseCount('services', 0);

        $this->post('/services', $payload + ['scheduled_start' => $start->toDateString(), 'scheduled_end' => $start->addMonthsNoOverflow(6)->toDateString()])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('services', ['quote_id' => $quote->id, 'scheduled_start' => $start->toDateString().' 00:00:00', 'scheduled_end' => $start->addMonthsNoOverflow(6)->toDateString().' 00:00:00']);
    }

    public function test_service_images_can_be_added_on_creation_and_edit_but_members_only_view_them(): void
    {
        Storage::fake('private');
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create(['status' => QuoteStatus::Approved]);

        $this->post('/services', [
            'quote_id' => $quote->id,
            'title' => 'Instalação',
            'images' => [UploadedFile::fake()->create('antes.png', 20, 'image/png')],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $service = Service::query()->where('quote_id', $quote->id)->firstOrFail();
        $first = $service->attachments()->firstOrFail();
        Storage::disk('private')->assertExists($first->path);
        $this->assertSame('image/png', $first->mime_type);

        $this->get("/services/{$service->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Edit')->where('service.id', $service->id)->has('attachments', 1));
        $this->post("/services/{$service->id}/attachments", ['file' => UploadedFile::fake()->create('depois.webp', 20, 'image/webp')])->assertSessionHasNoErrors();
        $this->assertSame(2, $service->attachments()->count());
        $this->get("/services/{$service->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Show')->where('canEdit', true)->has('attachments', 2));

        $member = User::factory()->create();
        $member->tenants()->attach($tenant, ['role' => 'member', 'status' => 'active']);
        $member->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($member)->withSession(['active_tenant_id' => $tenant->id, 'two_factor_passed_at' => now()->timestamp]);
        $this->get("/services/{$service->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Services/Show')->where('canEdit', false));
        $this->get("/attachments/{$first->id}/view")->assertOk()->assertHeader('content-type', 'image/png');
        $this->get("/services/{$service->id}/edit")->assertForbidden();
        $this->delete("/attachments/{$first->id}")->assertForbidden();
    }

    public function test_public_quote_history_contains_only_translated_client_events(): void
    {
        $tenant = $this->tenant();
        $quote = Quote::factory()->for($tenant)->for(Client::factory()->for($tenant)->create())->create();
        foreach (['sent', 'internal_policy_check', 'viewed'] as $event) {
            $quote->events()->create(['tenant_id' => $tenant->id, 'event' => $event, 'occurred_at' => now()]);
        }

        $this->post("/quotes/{$quote->id}/public-link")->assertRedirect();
        $history = $this->get($quote->refresh()->publicUrl())->viewData('page')['props']['quote']['history'];
        $this->assertSame(['Orçamento enviado', 'Orçamento visualizado'], array_column($history, 'description'));
        $this->assertArrayNotHasKey('event', $history[0]);
    }

    public function test_service_edit_preserves_existing_past_schedule_and_rejects_new_out_of_range_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 15:00:00', 'UTC'));
        $tenant = $this->tenant();
        $client = Client::factory()->for($tenant)->create();
        $service = Service::factory()->for($tenant)->for($client)->create([
            'scheduled_start' => '2026-10-01',
            'scheduled_end' => '2026-10-31',
        ]);

        $this->patch("/services/{$service->id}", [
            'title' => 'Título atualizado',
            'scheduled_start' => '2026-10-01',
            'scheduled_end' => '2026-10-31',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('Título atualizado', $service->refresh()->title);

        $this->patch("/services/{$service->id}", [
            'title' => 'Data inválida',
            'scheduled_start' => '2026-11-04',
            'scheduled_end' => '',
        ])->assertSessionHasErrors('scheduled_start');
        $this->assertSame('Título atualizado', $service->refresh()->title);
    }
}
