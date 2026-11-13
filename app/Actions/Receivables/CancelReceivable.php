<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Enums\ReceivableStatus;
use App\Models\ActivityLog;
use App\Models\Receivable;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelReceivable
{
    public function __construct(private readonly TenantContext $context) {}

    public function execute(Receivable $receivable, string $reason, int $userId): Receivable
    {
        return DB::transaction(function () use ($receivable, $reason, $userId): Receivable {
            $locked = Receivable::query()->where('tenant_id', $this->context->id())->whereKey($receivable->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [ReceivableStatus::Pending, ReceivableStatus::Overdue], true)) {
                throw ValidationException::withMessages(['reason' => 'Somente parcelas pendentes ou vencidas podem ser canceladas.']);
            }
            $locked->update(['status' => ReceivableStatus::Canceled, 'canceled_at' => now(), 'cancellation_reason' => trim($reason)]);
            ActivityLog::create(['tenant_id' => $locked->tenant_id, 'actor_user_id' => $userId, 'event' => 'receivable.canceled', 'subject_type' => Receivable::class, 'subject_id' => $locked->id, 'properties_json' => ['reason' => trim($reason)]]);

            return $locked->refresh();
        });
    }
}
