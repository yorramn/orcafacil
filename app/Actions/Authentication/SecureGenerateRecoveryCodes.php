<?php

declare(strict_types=1);

namespace App\Actions\Authentication;

use Illuminate\Support\Collection;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\RecoveryCode;

final class SecureGenerateRecoveryCodes
{
    /** @return list<string> */
    public function __invoke(object $user): array
    {
        $codes = Collection::times(8, fn (): string => RecoveryCode::generate())->all();
        $user->storeRecoveryCodes($codes);
        RecoveryCodesGenerated::dispatch($user);

        return $codes;
    }
}
