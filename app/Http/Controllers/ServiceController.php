<?php

namespace App\Http\Controllers;

use App\Actions\Services\ChangeServiceStatus;
use App\Actions\Services\ConvertQuoteToService;
use App\Enums\ServiceStatus;
use App\Http\Requests\ConvertQuoteRequest;
use App\Http\Requests\ServiceStatusRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Models\ActivityLog;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceTask;
use App\Services\Tenancy\TenantContext;
use App\Services\Services\ServiceImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceController extends Controller
{
    public function create(Request $request): Response
    {
        $this->authorize('create', Service::class);
        $tenant = app(TenantContext::class)->get();
        $quotes = $tenant->quotes()->where('status', 'approved')->with('service:id,quote_id')->orderByDesc('created_at')->get();
        $selectedQuote = null;
        if ($request->filled('quote')) {
            $selectedQuote = $quotes->firstWhere('id', $request->integer('quote'));
            abort_unless($selectedQuote !== null && $selectedQuote->service === null, 404);
        }

        return Inertia::render('Services/Create', [
            'quotes' => $quotes->filter(fn (Quote $quote): bool => $quote->service === null)->values(),
            'selectedQuoteId' => $selectedQuote?->id,
        ]);
    }

    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Service::class);
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['title', 'contracted_total_cents', 'scheduled_end', 'created_at'], true) ? $sort : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $services = Service::query()->where('tenant_id', $context->id())->with(['client', 'assignee'])->withExists(['billing', 'receivables'])
            ->when($request->filled('focus'), fn ($q) => $q->whereKey($request->integer('focus')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($query) => $query->where('title', 'like', '%'.$request->string('search').'%')->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$request->string('search').'%'))))
            ->orderBy($sort, $direction)->paginate(15)->withQueryString();

        $canManage = $request->user()->tenants()->whereKey($context->id())->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists();
        $services->getCollection()->each(fn (Service $service) => $service->setAttribute('canBill', $canManage && $this->canBill($service)));

        return Inertia::render('Services/Index', ['services' => $services, 'canManage' => $canManage, 'search' => $request->string('search')->toString(), 'focus' => $request->integer('focus'), 'status' => $request->string('status')->toString(), 'sort' => $sort, 'direction' => $direction]);
    }

    public function show(Service $service): Response
    {
        $this->authorize('view', $service);

        return Inertia::render('Services/Show', $this->detailData($service));
    }

    public function edit(Service $service): Response
    {
        $this->authorize('update', $service);

        return Inertia::render('Services/Edit', $this->detailData($service));
    }

    /** @return array<string, mixed> */
    private function detailData(Service $service): array
    {
        $service->load(['client', 'assignee', 'quote:id,number,title,created_at', 'tasks', 'notes', 'occurrences', 'attachments'])->loadExists(['billing', 'receivables']);
        $history = collect()
            ->merge($service->occurrences->map(fn ($entry): array => ['id' => 'occurrence-'.$entry->id, 'description' => $entry->description, 'occurred_at' => $entry->occurred_at, 'type' => 'occurrence']))
            ->merge($service->notes->map(fn ($entry): array => ['id' => 'note-'.$entry->id, 'description' => $entry->body, 'occurred_at' => $entry->created_at, 'type' => 'note']))
            ->merge($service->tasks->map(fn ($entry): array => ['id' => 'task-'.$entry->id, 'description' => $entry->title, 'occurred_at' => $entry->created_at, 'type' => 'task', 'status' => 'added']));
        $history = $history->merge(ActivityLog::query()
            ->where('tenant_id', $service->tenant_id)
            ->whereIn('subject_type', [Service::class, ServiceTask::class])
            ->whereIn('subject_id', [$service->id, ...$service->tasks->modelKeys()])
            ->latest('created_at')
            ->get()
            ->map(fn (ActivityLog $entry): array => ['id' => 'activity-'.$entry->id, 'description' => $entry->event, 'occurred_at' => $entry->created_at, 'type' => 'activity', 'properties' => $entry->properties_json]));

        $availableStatuses = array_values(array_map(
            fn (ServiceStatus $status): string => $status->value,
            array_filter(ServiceStatus::cases(), fn (ServiceStatus $status): bool => $service->status->canTransitionTo($status)),
        ));

        return ['service' => $service, 'canEdit' => request()->user()->can('update', $service), 'canBill' => request()->user()->can('update', $service) && $this->canBill($service), 'availableStatuses' => $availableStatuses, 'tasks' => $service->tasks, 'notes' => $service->notes, 'occurrences' => $service->occurrences, 'attachments' => $service->attachments, 'history' => $history->sortByDesc('occurred_at')->values()->all()];
    }

    private function canBill(Service $service): bool
    {
        return in_array($service->status, [ServiceStatus::InProgress, ServiceStatus::Completed], true)
            && ($service->status !== ServiceStatus::Completed || (int) $service->contracted_total_cents > 0)
            && ! $service->billing_exists && ! $service->receivables_exists;
    }

    public function store(ConvertQuoteRequest $request, ConvertQuoteToService $action, ServiceImageService $images): RedirectResponse
    {
        $quote = Quote::query()->where('tenant_id', app(TenantContext::class)->id())->findOrFail($request->integer('quote_id'));
        $service = $action->execute($quote, $request->validated(), $request->user()->id);
        foreach ($request->file('images', []) as $file) {
            $images->store($service, $file, $request->user()->id);
        }

        return redirect()->route('services.index', ['focus' => $service->id, 'search' => $service->title])
            ->with('success', 'Serviço criado com sucesso!');
    }

    public function convert(ConvertQuoteRequest $request, Quote $quote, ConvertQuoteToService $action, ServiceImageService $images): RedirectResponse
    {
        $this->authorize('convert', $quote);
        $service = $action->execute($quote, $request->validated(), $request->user()->id);
        foreach ($request->file('images', []) as $file) {
            $images->store($service, $file, $request->user()->id);
        }

        return redirect()->route('services.index', ['focus' => $service->id, 'search' => $service->title])
            ->with('success', 'Serviço criado com sucesso!');
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());
        if ($service->wasChanged()) {
            ActivityLog::create(['tenant_id' => $service->tenant_id, 'actor_user_id' => $request->user()->id, 'event' => 'service.updated', 'subject_type' => Service::class, 'subject_id' => $service->id]);
        }

        return redirect()->route('services.index', ['focus' => $service->id, 'search' => $service->title])
            ->with('success', 'Serviço editado com sucesso!');
    }

    public function status(ServiceStatusRequest $request, Service $service, ChangeServiceStatus $action): RedirectResponse
    {
        $this->authorize('update', $service);
        $action->execute($service, ServiceStatus::from($request->string('status')->toString()), $request->user()->id);

        return redirect()->route('services.index', ['focus' => $service->id, 'search' => $service->title])
            ->with('success', 'Serviço editado com sucesso!');
    }
}
