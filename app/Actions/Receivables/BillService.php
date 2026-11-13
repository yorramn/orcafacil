<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Enums\ReceivableStatus;
use App\Enums\ServiceStatus;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\ServiceBilling;
use App\Services\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillService
{
    public function __construct(private readonly TenantContext $context) {}

    /** @param array<int, array{description:string,due_date:string,amount_cents:int}> $installments */
    public function execute(Service $service, array $installments, int $userId): ServiceBilling
    {
        return DB::transaction(function () use ($service, $installments, $userId): ServiceBilling {
            $locked = Service::query()->where('tenant_id', $this->context->id())->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->billing()->first();
            if ($existing !== null) {
                return $existing;
            }
            if (! in_array($locked->status, [ServiceStatus::InProgress, ServiceStatus::Completed], true)) {
                throw ValidationException::withMessages(['service' => 'Somente serviços em andamento ou concluídos podem ser faturados.']);
            }
            if ($locked->receivables()->exists()) {
                throw ValidationException::withMessages(['service' => 'Este serviço já possui recebimentos vinculados. Confira-os antes de faturar.']);
            }

            $total = (int) $locked->contracted_total_cents;
            if ($total === 0 && count($installments) !== 0) {
                throw ValidationException::withMessages(['installments' => 'Serviços sem valor não geram parcelas.']);
            }
            if ($total > 0 && (count($installments) < 1 || count($installments) > 60)) {
                throw ValidationException::withMessages(['installments' => 'Informe entre 1 e 60 parcelas.']);
            }
            if (array_sum(array_column($installments, 'amount_cents')) !== $total) {
                throw ValidationException::withMessages(['installments' => 'A soma das parcelas deve ser igual ao valor contratado.']);
            }

            $billing = $locked->billing()->create([
                'tenant_id' => $locked->tenant_id,
                'billed_by' => $userId,
                'total_cents' => $total,
                'billed_at' => now(),
            ]);
            $today = CarbonImmutable::today('America/Sao_Paulo')->toDateString();
            foreach ($installments as $index => $installment) {
                $locked->receivables()->create([
                    'tenant_id' => $locked->tenant_id,
                    'service_billing_id' => $billing->id,
                    'client_id' => $locked->client_id,
                    'quote_id' => $locked->quote_id,
                    'installment_number' => $index + 1,
                    'description' => $installment['description'],
                    'status' => $installment['due_date'] < $today ? ReceivableStatus::Overdue : ReceivableStatus::Pending,
                    'due_date' => $installment['due_date'],
                    'amount_cents' => $installment['amount_cents'],
                    'paid_amount_cents' => 0,
                ]);
            }
            if ($locked->status === ServiceStatus::InProgress) {
                $locked->update(['status' => ServiceStatus::Completed, 'completed_at' => now()]);
                ActivityLog::create(['tenant_id' => $locked->tenant_id, 'actor_user_id' => $userId, 'event' => 'service.status_changed', 'subject_type' => Service::class, 'subject_id' => $locked->id, 'properties_json' => ['status' => ServiceStatus::Completed->value]]);
            }
            ActivityLog::create(['tenant_id' => $locked->tenant_id, 'actor_user_id' => $userId, 'event' => 'service.billed', 'subject_type' => Service::class, 'subject_id' => $locked->id, 'properties_json' => ['billing_id' => $billing->id, 'total_cents' => $total]]);

            return $billing;
        });
    }
}
