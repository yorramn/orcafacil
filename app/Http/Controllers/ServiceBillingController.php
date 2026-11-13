<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Receivables\BillService;
use App\Enums\ServiceStatus;
use App\Http\Requests\BillServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ServiceBillingController extends Controller
{
    public function show(Service $service): Response
    {
        $this->authorize('update', $service);
        abort_unless(in_array($service->status, [ServiceStatus::InProgress, ServiceStatus::Completed], true), 404);
        $service->load(['client', 'quote', 'receivables']);

        return Inertia::render('Services/Billing', [
            'service' => $service,
            'billingExists' => $service->billing()->exists() || $service->receivables->isNotEmpty(),
            'billingMode' => $service->status === ServiceStatus::InProgress ? 'complete' : 'retroactive',
        ]);
    }

    public function store(BillServiceRequest $request, Service $service, BillService $action): RedirectResponse
    {
        $action->execute($service, $request->validated('installments'), $request->user()->id);

        return redirect()->route('services.show', $service)->with('success', 'Serviço faturado com sucesso!');
    }
}
