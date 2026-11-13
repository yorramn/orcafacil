<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tenancy\CreateTenant;
use App\Http\Requests\TenantOnboardingRequest;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TenantOnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $tenants = $request->user()->tenants()->wherePivot('status', 'active')->get(['tenants.id', 'tenants.name', 'tenants.slug']);
        $tenant = $tenants->first();
        if ($tenants->isNotEmpty()) {
            return Inertia::render('Onboarding/Tenant', [
                'step' => 'select',
                'tenant' => $tenant,
                'tenants' => $tenants,
                'can_create' => false,
                'selection' => ['method' => 'POST', 'url' => $tenant ? route('tenant.select', $tenant) : null],
            ]);
        }

        $this->authorize('create', Tenant::class);

        return Inertia::render('Onboarding/Tenant', [
            'step' => 'create',
            'tenant' => null,
            'tenants' => [],
            'can_create' => true,
            'plan' => ['name' => 'Básico', 'price' => 'R$ 15/mês'],
            'form' => ['method' => 'POST', 'url' => route('onboarding.tenant.store'), 'fields' => ['name', 'slug', 'timezone', 'currency']],
        ]);
    }

    public function store(TenantOnboardingRequest $request, CreateTenant $create): RedirectResponse
    {
        $tenant = $create->execute($request->user(), $request->validated());
        $request->session()->put('active_tenant_id', $tenant->getKey());

        return redirect()->route('dashboard')->with('status', 'tenant-created');
    }

    public function select(Request $request, Tenant $tenant, TenantContext $context): RedirectResponse
    {
        $this->authorize('select', $tenant);
        $context->set($tenant);
        $request->session()->put('active_tenant_id', $tenant->getKey());

        return redirect()->route('dashboard')->with('status', 'tenant-selected');
    }
}
