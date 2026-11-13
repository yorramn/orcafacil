<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class PublicQuoteThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $key = 'public-quote:'.hash('sha256', $token.'|'.$request->ip());
        $maxAttempts = 30;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = max(1, (int) RateLimiter::availableIn($key));
            $response = Inertia::render('Public/Quote', [
                'state' => 'rate_limited',
                'message' => 'Muitas tentativas. Aguarde e tente novamente.',
                'token' => $token,
                'quote' => null,
                'retry_after_seconds' => $retryAfter,
            ])->toResponse($request);

            return $response->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS)->withHeaders([
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
