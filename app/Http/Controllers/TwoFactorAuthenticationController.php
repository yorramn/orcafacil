<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Authentication\SecureEnableTwoFactorAuthentication;
use App\Actions\Authentication\SecureGenerateRecoveryCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

final class TwoFactorAuthenticationController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $enabled = $user->hasEnabledTwoFactorAuthentication();

        return Inertia::render('Auth/TwoFactorSetup', [
            'twoFactor' => [
                'configured' => $enabled,
                'qr_svg' => ! $enabled && $user->two_factor_secret ? $user->twoFactorQrCodeSvg() : null,
                'manual_key' => ! $enabled && $user->two_factor_secret ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
                'recovery_codes' => $request->session()->pull('two_factor_recovery_codes'),
            ],
        ]);
    }

    public function enable(Request $request, SecureEnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $codes = $enable($request->user());
        $request->session()->put('two_factor_pending_recovery_codes', $codes);
        $request->session()->put('two_factor_setup_authorized_at', now()->toIso8601String());

        return back()->with('status', 'two-factor-secret-generated');
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        abort_unless($request->session()->has('two_factor_setup_authorized_at'), 403, 'Reautenticação necessária antes de confirmar o 2FA.');
        $confirm($request->user(), $validated['code']);
        $request->session()->forget('two_factor_setup_authorized_at');
        $request->session()->put('two_factor_passed_at', now()->toIso8601String());
        $request->session()->flash('two_factor_recovery_codes', $request->session()->pull('two_factor_pending_recovery_codes', []));

        return back()->with('status', 'two-factor-confirmed');
    }

    public function recoveryCodes(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user();
        abort_unless($this->validSecondFactor($user, $validated['code'], $provider), 422, 'The provided two-factor code is invalid.');
        $codes = app(SecureGenerateRecoveryCodes::class)($request->user());
        $request->session()->flash('two_factor_recovery_codes', $codes);

        return back()->with('status', 'recovery-codes-regenerated');
    }

    public function disable(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        if ($request->has('password') && ! $request->has('current_password')) {
            $request->merge(['current_password' => $request->input('password')]);
        }
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user();
        abort_unless($this->validSecondFactor($user, $validated['code'], $provider), 422, 'The provided two-factor code is invalid.');
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget('two_factor_passed_at');

        return back()->with('status', 'two-factor-disabled');
    }

    private function validSecondFactor(object $user, string $code, TwoFactorAuthenticationProvider $provider): bool
    {
        $validCode = ctype_digit($code) && strlen($code) === 6
            && $user->two_factor_secret
            && $provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code);
        $validRecovery = $user->two_factor_recovery_codes && in_array($code, $user->recoveryCodes(), true);

        return $validCode || $validRecovery;
    }
}
