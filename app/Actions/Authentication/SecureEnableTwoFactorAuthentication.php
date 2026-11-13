<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;

final class SecureEnableTwoFactorAuthentication
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $provider) {}

    /** @return list<string> */
    public function __invoke(object $user, bool $force = false): array
    {
        if (! empty($user->two_factor_secret) && ! $force) {
            return [];
        }

        $secretLength = (int) config('fortify-options.two-factor-authentication.secret-length', 16);
        $codes = Collection::times(8, fn (): string => RecoveryCode::generate())->all();
        $user->forceFill(['two_factor_secret' => Fortify::currentEncrypter()->encrypt($this->provider->generateSecretKey($secretLength))])->save();
        $user->storeRecoveryCodes($codes);
        TwoFactorAuthenticationEnabled::dispatch($user);

        return $codes;
    }
}
