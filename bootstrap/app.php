<?php

use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\EnsureTwoFactorIsConfigured;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PublicQuoteThrottle;
use App\Jobs\MarkOverdueReceivables;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job(new MarkOverdueReceivables)->dailyAt('01:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            'two-factor.configured' => EnsureTwoFactorIsConfigured::class,
            'public-quote' => PublicQuoteThrottle::class,
        ]);
        $middleware->validateCsrfTokens(except: ['billing/webhook']);
        $middleware->web(append: [HandleInertiaRequests::class]);
        $trustedProxyConfig = require __DIR__.'/../config/trustedproxy.php';
        $middleware->trustProxies(at: $trustedProxyConfig['proxies']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() !== 403 || $request->expectsJson()) {
                return null;
            }

            return Inertia::render('Errors/Forbidden')
                ->toResponse($request)
                ->setStatusCode(403);
        });
    })->create();
