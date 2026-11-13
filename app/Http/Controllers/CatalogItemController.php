<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Catalog\CreateCatalogItem;
use App\Actions\Catalog\UpdateCatalogItem;
use App\Http\Requests\CatalogItemRequest;
use App\Models\CatalogItem;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CatalogItemController extends Controller
{
    public function create(): Response
    {
        $this->authorize('create', CatalogItem::class);

        return Inertia::render('Catalog/Create');
    }

    public function edit(CatalogItem $catalogItem): Response
    {
        $this->authorize('update', $catalogItem);

        return Inertia::render('Catalog/Edit', ['item' => $catalogItem]);
    }

    public function show(CatalogItem $catalogItem, TenantContext $context): Response
    {
        abort_unless((int) $catalogItem->tenant_id === $context->id(), 404);
        $this->authorize('view', $catalogItem);

        return Inertia::render('Catalog/Show', ['item' => $catalogItem]);
    }

    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', CatalogItem::class);
        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['name', 'type', 'default_unit_price_cents', 'created_at'], true) ? $sort : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $status = $request->string('status')->toString();
        $status = in_array($status, ['active', 'inactive'], true) ? $status : ($request->filled('active') ? ($request->boolean('active') ? 'active' : 'inactive') : '');
        $items = CatalogItem::query()->forTenant($context->id())
            ->when($request->filled('focus'), fn ($q) => $q->whereKey($request->integer('focus')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%')->orWhere('description', 'like', '%'.$request->string('search').'%')))
            ->when($status !== '', fn ($q) => $q->where('active', $status === 'active'))
            ->orderBy($sort, $direction)->paginate(20)->withQueryString();

        return Inertia::render('Catalog/Index', [
            'items' => $items,
            'canManage' => $request->user()->tenants()->whereKey($context->id())->wherePivotIn('role', ['owner', 'admin'])->wherePivot('status', 'active')->exists(),
            'search' => $request->string('search')->toString(),
            'focus' => $request->integer('focus'),
            'active' => $request->filled('active') ? ($request->boolean('active') ? 'active' : 'inactive') : '',
            'status' => $status,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function store(CatalogItemRequest $request, CreateCatalogItem $create): RedirectResponse
    {
        $this->authorize('create', CatalogItem::class);
        $item = $create->execute($request->validated());

        return redirect()->route('catalog.index', ['focus' => $item->id, 'search' => $item->name, 'status' => $item->active ? 'active' : 'inactive'])
            ->with('success', 'Item do catálogo criado com sucesso!');
    }

    public function update(CatalogItemRequest $request, CatalogItem $catalogItem, UpdateCatalogItem $update): RedirectResponse
    {
        $this->authorize('update', $catalogItem);
        $item = $update->execute($catalogItem, $request->validated());

        return redirect()->route('catalog.index', ['focus' => $item->id, 'search' => $item->name, 'status' => $item->active ? 'active' : 'inactive'])
            ->with('success', 'Item do catálogo editado com sucesso!');
    }

    public function destroy(CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorize('delete', $catalogItem);
        $catalogItem->update(['active' => false]);

        return redirect()->route('catalog.index', ['focus' => $catalogItem->id, 'search' => $catalogItem->name, 'status' => 'inactive'])
            ->with('success', 'Item do catálogo excluído com sucesso!');
    }
}
