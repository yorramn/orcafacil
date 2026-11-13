<?php

namespace App\Actions\Services;

use App\Enums\QuoteStatus;
use App\Models\ActivityLog;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConvertQuoteToService
{
    public function __construct(private readonly TenantContext $context) {}

    public function execute(Quote $quote, array $data, int $userId): Service
    {
        return DB::transaction(function () use ($quote, $data, $userId) {
            $locked = Quote::query()->where('tenant_id', $this->context->id())->whereKey($quote->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== QuoteStatus::Approved) {
                throw ValidationException::withMessages(['quote' => 'Somente orçamentos aprovados podem virar serviços.']);
            }
            if (($data['assigned_to'] ?? null) !== null && ! User::query()->whereKey($data['assigned_to'])->whereHas('tenants', fn ($q) => $q->whereKey($locked->tenant_id)->wherePivot('status', 'active'))->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Responsável inválido para o tenant atual.']);
            }
            $existing = Service::query()->where('tenant_id', $locked->tenant_id)->where('quote_id', $locked->id)->first();
            if ($existing) {
                return $existing->load(['client', 'tasks', 'notes']);
            }
            $plan = $this->context->get()->subscriptions()->latest()->value('plan');
            if ($plan === 'basic' && Service::query()->where('tenant_id', $locked->tenant_id)->whereIn('status', ['planned', 'in_progress', 'paused'])->count() >= 10) {
                throw ValidationException::withMessages(['service' => 'Limite de serviços em andamento atingido.']);
            }
            $client = $locked->client;
            $service = Service::create(['tenant_id' => $locked->tenant_id, 'client_id' => $client->id, 'quote_id' => $locked->id, 'assigned_to' => $data['assigned_to'] ?? null, 'title' => $data['title'] ?? $locked->title, 'description' => $data['description'] ?? $locked->description, 'status' => 'planned', 'contracted_total_cents' => $locked->total_cents, 'scheduled_start' => $data['scheduled_start'] ?? null, 'scheduled_end' => $data['scheduled_end'] ?? null, 'address_snapshot_json' => $client->only(['address', 'address_number', 'address_complement', 'neighborhood', 'city', 'state', 'postal_code'])]);
            ActivityLog::create(['tenant_id' => $locked->tenant_id, 'actor_user_id' => $userId, 'event' => 'service.created', 'subject_type' => Service::class, 'subject_id' => $service->id]);
            foreach (($data['tasks'] ?? []) as $position => $task) {
                $service->tasks()->create(['tenant_id' => $locked->tenant_id, 'title' => $task['title'], 'description' => $task['description'] ?? null, 'position' => $position]);
            } $locked->events()->create(['tenant_id' => $locked->tenant_id, 'actor_user_id' => $userId, 'event' => 'converted_to_service', 'metadata_json' => ['service_id' => $service->id], 'occurred_at' => now()]);

            return $service->load(['client', 'tasks', 'notes']);
        });
    }
}
