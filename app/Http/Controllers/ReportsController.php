<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\Reports\ReportService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportsController extends Controller
{
    public function index(Request $request, TenantContext $context, ReportService $reports): Response
    {
        $tenant = $context->get();
        $this->authorize('viewReports', $tenant);
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $report = $reports->build($tenant, $validated['from'] ?? null, $validated['to'] ?? null);

        return Inertia::render('Reports/Index', [
            'metrics' => ['receivable_cents' => $report['receivables']['expected_cents'], 'received_cents' => $report['receivables']['received_cents']],
            'report' => $report,
        ]);
    }

    public function activity(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewActivity', $context->get());

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['event', 'created_at'], true) ? $sort : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $category = $request->string('status')->toString();
        $prefixes = ['quotes' => ['quote.%'], 'services' => ['service.%'], 'receivables' => ['receivable.%']];
        $activity = ActivityLog::where('tenant_id', $context->id())->with('actor')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($query) => $query->where('event', 'like', '%'.$request->string('search').'%')->orWhereHas('actor', fn ($actor) => $actor->where('name', 'like', '%'.$request->string('search').'%'))))
            ->when(isset($prefixes[$category]), fn ($q) => $q->where('event', 'like', $prefixes[$category][0]))
            ->when($category === 'other', fn ($q) => $q->where('event', 'not like', 'quote.%')->where('event', 'not like', 'service.%')->where('event', 'not like', 'receivable.%'))
            ->orderBy($sort, $direction)->paginate(30)->withQueryString();

        return Inertia::render('Activity/Index', ['activity' => $activity, 'search' => $request->string('search')->toString(), 'status' => $category, 'sort' => $sort, 'direction' => $direction]);
    }

    public function export(Request $request, TenantContext $context, ReportService $reports): StreamedResponse
    {
        $tenant = $context->get();
        $this->authorize('viewReports', $tenant);
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return $reports->exportCsv($tenant, $validated['from'] ?? null, $validated['to'] ?? null);
    }
}
