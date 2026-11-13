<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\UpdateClient;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ClientController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('Clients/Create');
    }

    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Client::class);
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['name', 'created_at', 'updated_at'], true) ? $sort : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $clients = Client::query()->forTenant($context->id())->search($request->string('search')->toString())
            ->when($request->filled('focus'), fn ($q) => $q->whereKey($request->integer('focus')))
            ->when($request->string('status')->toString() === 'archived', fn ($q) => $q->withTrashed()->whereNotNull('deleted_at'))
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->where('status', 'active'))
            ->orderBy($sort, $direction)->paginate(20)->withQueryString();

        return Inertia::render('Clients/Index', ['clients' => $clients, 'canManage' => $request->user()->tenants()->whereKey($context->id())->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists(), 'search' => $request->string('search')->toString(), 'focus' => $request->integer('focus'), 'status' => $request->string('status')->toString(), 'sort' => $sort, 'direction' => $direction]);
    }

    public function store(ClientRequest $request, CreateClient $create): RedirectResponse
    {
        $this->authorize('create', Client::class);
        $client = $create->execute($request->validated());

        return redirect()->route('clients.index', ['focus' => $client->id, 'search' => $client->name, 'status' => 'active'])
            ->with('success', 'Cliente criado com sucesso!');
    }

    public function update(ClientRequest $request, Client $client, UpdateClient $update): RedirectResponse
    {
        $this->authorize('update', $client);
        $client = $update->execute($client, $request->validated());

        return redirect()->route('clients.index', ['focus' => $client->id, 'search' => $client->name])
            ->with('success', 'Cliente editado com sucesso!');
    }

    public function show(Request $request, Client $client): Response
    {
        $this->authorize('view', $client);

        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, ['quotes', 'services', 'receivables'], true) ? $tab : 'quotes';

        return Inertia::render('Clients/Show', [
            'client' => $client,
            'tab' => $tab,
            'quotes' => $client->quotes()->latest()->paginate(10, ['*'], 'quotes_page')->withQueryString(),
            'services' => $client->services()->latest()->paginate(10, ['*'], 'services_page')->withQueryString(),
            'receivables' => $client->receivables()->latest()->paginate(10, ['*'], 'receivables_page')->withQueryString(),
        ]);
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', ['client' => $client]);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);
        $client->delete();

        return redirect()->route('clients.index', ['focus' => $client->id, 'search' => $client->name, 'status' => 'archived'])
            ->with('success', 'Cliente excluído com sucesso!');
    }
}
