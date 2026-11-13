<?php

namespace App\Services\Dashboard;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Receivable;
use App\Models\Service;
use App\Models\Tenant;

final class DashboardService
{
    public function build(Tenant $tenant): array
    {
        $id = $tenant->getKey();
        $received = Receivable::where('tenant_id', $id)->where('status', 'paid')->sum('paid_amount_cents');
        $open = Receivable::where('tenant_id', $id)->whereIn('status', ['pending', 'overdue'])->sum('amount_cents');

        return ['kpis' => [['key' => 'pending_quotes', 'label' => 'Aguardando resposta', 'value' => Quote::where('tenant_id', $id)->whereIn('status', ['sent', 'viewed'])->count(), 'detail' => 'propostas'], ['key' => 'approved', 'label' => 'Aprovados no período', 'value' => Quote::where('tenant_id', $id)->where('status', 'approved')->where('approved_at', '>=', now()->startOfMonth())->count(), 'detail' => 'este mês'], ['key' => 'services', 'label' => 'Serviços em andamento', 'value' => Service::where('tenant_id', $id)->whereIn('status', ['planned', 'in_progress', 'paused'])->count(), 'detail' => 'execuções'], ['key' => 'receivable', 'label' => 'A receber', 'value' => 'R$ '.number_format($open / 100, 2, ',', '.'), 'detail' => 'Receita recebida: R$ '.number_format($received / 100, 2, ',', '.')]], 'activity' => ActivityLog::where('tenant_id', $id)->with('actor')->latest()->limit(10)->get()->map(fn ($a) => ['id' => $a->id, 'event' => $a->event, 'description' => $a->event, 'occurred_at' => $a->created_at?->toIso8601String()])->values(), 'quickActions' => [['href' => '/clients', 'label' => 'Clientes', 'description' => Client::where('tenant_id', $id)->count().' cadastrados'], ['href' => '/receivables', 'label' => 'Recebimentos', 'description' => 'Acompanhe parcelas']], 'tenant' => $tenant->only(['id', 'name', 'timezone'])];
    }
}
