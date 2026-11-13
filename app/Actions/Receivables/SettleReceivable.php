<?php

namespace App\Actions\Receivables;

use App\Enums\ReceivableStatus;
use App\Models\ActivityLog;
use App\Models\Receivable;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SettleReceivable
{
    public function __construct(private readonly TenantContext $context) {}

    public function execute(Receivable $receivable, array $data, int $userId): Receivable
    {
        return DB::transaction(function () use ($receivable, $data, $userId) {
            $r = Receivable::query()->where('tenant_id', $this->context->id())->whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            if ($r->status === ReceivableStatus::Paid) {
                return $r;
            } abort_unless(in_array($r->status, [ReceivableStatus::Pending, ReceivableStatus::Overdue], true), 422, 'Recebível não pode receber baixa.');
            $paidAt = isset($data['paid_at'])
                ? CarbonImmutable::parse($data['paid_at'], 'America/Sao_Paulo')->setTime(12, 0)->utc()
                : now();
            $r->update(['status' => ReceivableStatus::Paid, 'paid_amount_cents' => $r->amount_cents, 'paid_at' => $paidAt, 'payment_method' => $data['payment_method'] ?? null, 'notes' => $data['notes'] ?? $r->notes]);
            ActivityLog::create(['tenant_id' => $r->tenant_id, 'actor_user_id' => $userId, 'event' => 'receivable.paid', 'subject_type' => Receivable::class, 'subject_id' => $r->id, 'properties_json' => ['amount_cents' => $r->paid_amount_cents]]);

            return $r->refresh();
        });
    }
}
