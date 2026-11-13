<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\TwoFactorEmailRecoveryCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class TwoFactorEmailRecoveryController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $user = $request->session()->get('login.id') ? User::find($request->session()->get('login.id')) : null;
        abort_unless($user?->hasVerifiedEmail() && $user->hasEnabledTwoFactorAuthentication(), 403);
        $key = 'two-factor-email:'.$user->getKey().'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429);
        RateLimiter::hit($key, 600);
        $code = (string) random_int(100000, 999999);
        $request->session()->put('two_factor_email_recovery', [
            'hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'user_id' => $user->getKey(),
            'masked_email' => $this->maskEmail((string) $user->email),
        ]);
        $user->notify(new TwoFactorEmailRecoveryCode($code));

        return back()->with('status', 'two-factor-recovery-sent');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $data = $request->session()->get('two_factor_email_recovery');
        if (! is_array($data) || ($data['expires_at'] ?? 0) < now()->timestamp || ! Hash::check($validated['code'], $data['hash'] ?? '')) {
            return back()->withErrors(['code' => 'Código inválido ou expirado. Solicite um novo código e tente novamente.']);
        }
        $user = User::findOrFail($data['user_id']);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget(['login.id', 'two_factor_email_recovery']);
        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('two-factor.setup')->with('status', 'two-factor-recovered');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }
}
