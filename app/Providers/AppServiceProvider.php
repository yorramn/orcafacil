<?php

namespace App\Providers;

use App\Actions\Authentication\SecureEnableTwoFactorAuthentication;
use App\Actions\Authentication\SecureGenerateRecoveryCodes;
use App\Actions\Fortify\CreateNewUser;
use App\Policies\TeamPolicy;
use App\Services\Billing\BillingProvider;
use App\Services\Billing\FakeBillingProvider;
use App\Services\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CreatesNewUsers::class, CreateNewUser::class);
        // Scoped prevents the active tenant from leaking between requests in Octane/workers.
        $this->app->scoped(TenantContext::class);
        $this->app->bind(BillingProvider::class, FakeBillingProvider::class);
        $this->app->bind(EnableTwoFactorAuthentication::class, SecureEnableTwoFactorAuthentication::class);
        $this->app->bind(GenerateNewRecoveryCodes::class, SecureGenerateRecoveryCodes::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::loginView(fn () => Inertia::render('Auth/Login'));
        Fortify::registerView(fn () => Inertia::render('Auth/Register'));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword'));
        Fortify::resetPasswordView(fn ($request) => Inertia::render('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));
        Fortify::twoFactorChallengeView(function () {
            $recovery = request()->session()->get('two_factor_email_recovery', []);

            return Inertia::render('Auth/TwoFactorChallenge', [
                'recoveryPending' => is_array($recovery) && ($recovery['expires_at'] ?? 0) >= now()->timestamp,
                'recoveryEmail' => $recovery['masked_email'] ?? null,
            ]);
        });
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id').'|'.$request->ip()));
        RateLimiter::for('invitations', fn (Request $request) => Limit::perMinute(10)->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()));

        Gate::define('team.view', [TeamPolicy::class, 'view']);
        Gate::define('team.invite', [TeamPolicy::class, 'invite']);
        Gate::define('team.remove', [TeamPolicy::class, 'remove']);

        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, function (): void {
            request()->session()->put('two_factor_passed_at', now()->toIso8601String());
        });
    }
}
