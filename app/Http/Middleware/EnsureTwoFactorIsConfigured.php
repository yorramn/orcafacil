<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTwoFactorIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('two-factor.setup');
        }

        if (! $request->session()->has('two_factor_passed_at')) {
            return redirect()->route('two-factor.login');
        }

        return $next($request);
    }
}
