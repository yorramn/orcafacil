<?php

namespace App\Actions\Services;

use App\Enums\ServiceStatus;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeServiceStatus
{
    public function __construct(private readonly TenantContext $context) {}

    public function execute(Service $service, ServiceStatus $status, int $userId): Service
    {
        return DB::transaction(function () use ($service, $status, $userId) {
            $locked = Service::query()->where('tenant_id', $this->context->id())->whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($status === ServiceStatus::Completed) {
                throw ValidationException::withMessages(['status' => 'Configure as parcelas para concluir e faturar o serviço.']);
            }
            if (! $locked->status->canTransitionTo($status)) {
                throw ValidationException::withMessages(['status' => 'Transição de status inválida.']);
            } $locked->update(['status' => $status, 'completed_at' => $status === ServiceStatus::Completed ? now() : null]);
            ActivityLog::create(['tenant_id' => $locked->tenant_id, 'actor_user_id' => $userId, 'event' => 'service.status_changed', 'subject_type' => Service::class, 'subject_id' => $locked->id, 'properties_json' => ['status' => $status->value]]);

            return $locked->refresh();
        });
    }
}
