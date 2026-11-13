<?php

namespace App\Http\Controllers;

use App\Services\Billing\BillingService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function show(TenantContext $context, BillingService $billing): Response
    {
        $tenant = $context->get();
        $this->authorize('manageBilling', $tenant);
        $subscription = $tenant->subscriptions()->latest()->first() ?? $billing->start($tenant);

        return Inertia::render('Settings/Billing', ['subscription' => $subscription, 'usage' => $billing->limits($tenant), 'plans' => collect(BillingService::PLANS)->map(fn ($p, $key) => ['key' => $key, 'name' => $p['name'], 'price' => 'R$ '.number_format($p['price_cents'] / 100, 2, ',', '.')])->values()]);
    }

    public function change(Request $request, TenantContext $context, BillingService $billing): RedirectResponse
    {
        $request->validate(['plan' => ['required', 'in:basic,complete']]);
        $s = $context->get()->subscriptions()->latest()->firstOrFail();
        $this->authorize('manageBilling', $context->get());
        $billing->change($s, $request->string('plan')->toString());

        return back()->with('success', 'Plano atualizado.');
    }

    public function cancel(TenantContext $context, BillingService $billing): RedirectResponse
    {
        $s = $context->get()->subscriptions()->latest()->firstOrFail();
        $this->authorize('manageBilling', $context->get());
        $billing->cancel($s);

        return back()->with('success', 'Assinatura cancelada; seus dados foram preservados.');
    }

    public function webhook(Request $request, BillingService $billing): \Illuminate\Http\Response
    {
        $raw = $request->getContent();
        abort_unless($billing->verifyWebhook($raw, $request->header('X-Billing-Signature')), 401);
        $billing->processWebhook($request->json()->all());

        return response()->noContent();
    }
}
