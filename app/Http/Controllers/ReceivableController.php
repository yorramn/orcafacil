<?php

namespace App\Http\Controllers;

use App\Actions\Receivables\SettleReceivable;
use App\Actions\Receivables\CancelReceivable;
use App\Enums\ReceivableStatus;
use App\Http\Requests\CancelReceivableRequest;
use App\Http\Requests\SettleReceivableRequest;
use App\Models\Receivable;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReceivableController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Receivable::class);
        $base = Receivable::query()->where('tenant_id', $context->id());
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['due_date', 'amount_cents', 'created_at'], true) ? $sort : 'due_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $rows = $this->filtered($request, clone $base)->with(['client', 'service:id,title'])
            ->orderBy($sort, $direction)->paginate(20)->withQueryString();

        $remaining = DB::raw('CASE WHEN amount_cents > paid_amount_cents THEN amount_cents - paid_amount_cents ELSE 0 END');
        $canManage = $request->user()->tenants()->whereKey($context->id())->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();

        return Inertia::render('Receivables/Index', ['receivables' => $rows, 'canManage' => $canManage, 'summary' => [
            'pending' => self::formatCents((int) (clone $base)->whereIn('status', [ReceivableStatus::Pending, ReceivableStatus::Overdue])->sum($remaining)),
            'overdue' => self::formatCents((int) (clone $base)->where('status', ReceivableStatus::Overdue)->sum($remaining)),
            'paid' => self::formatCents((int) (clone $base)->where('status', ReceivableStatus::Paid)->sum('paid_amount_cents')),
        ], 'search' => $request->string('search')->toString(), 'status' => $request->string('status')->toString(), 'sort' => $sort, 'direction' => $direction]);
    }

    public function settle(SettleReceivableRequest $request, Receivable $receivable, SettleReceivable $action): RedirectResponse
    {
        $this->authorize('settle', $receivable);
        $action->execute($receivable, $request->validated(), $request->user()->id);

        return back()->with('success', 'Recebimento registrado.');
    }

    public function export(Request $request, TenantContext $context): StreamedResponse
    {
        $this->authorize('viewAny', Receivable::class);
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['due_date', 'amount_cents', 'created_at'], true) ? $sort : 'due_date';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $rows = $this->filtered($request, Receivable::query()->where('tenant_id', $context->id()))
            ->with(['client', 'service:id,title'])->orderBy($sort, $direction)->lazy(200);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'cliente', 'servico', 'descricao', 'parcela', 'vencimento', 'valor_centavos', 'status', 'pago_centavos', 'data_pagamento', 'motivo_cancelamento']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, self::safeCsv($r->client?->name), self::safeCsv($r->service?->title), self::safeCsv($r->description), $r->installment_number, $r->due_date?->format('d/m/Y'), $r->amount_cents, $r->status->value, $r->paid_amount_cents, $r->paid_at?->timezone('America/Sao_Paulo')->format('d/m/Y'), self::safeCsv($r->cancellation_reason)]);
            } fclose($out);
        }, 'receivables.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function cancel(CancelReceivableRequest $request, Receivable $receivable, CancelReceivable $action): RedirectResponse
    {
        $this->authorize('cancel', $receivable);
        $action->execute($receivable, $request->string('reason')->toString(), $request->user()->id);

        return back()->with('success', 'Recebível cancelado.');
    }

    private function filtered(Request $request, Builder $query): Builder
    {
        return $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner->where('description', 'like', '%'.$request->string('search').'%')->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$request->string('search').'%'))));
    }

    private static function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    private static function safeCsv(?string $value): ?string
    {
        return $value !== null && preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
