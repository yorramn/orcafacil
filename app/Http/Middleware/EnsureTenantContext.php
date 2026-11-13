<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            abort(401);
        }

        $selectedTenantId = (int) $request->session()->get('active_tenant_id', 0);
        $tenants = $request->user()->tenants()->wherePivot('status', 'active');
        $tenant = $selectedTenantId > 0 ? (clone $tenants)->whereKey($selectedTenantId)->first() : null;
        $tenant ??= $tenants->first();

        if ($tenant === null) {
            $request->session()->forget('active_tenant_id');
            abort(403, 'An active tenant association is required.');
        }
        $this->context->set($tenant);

        return $next($request);
    }
}
