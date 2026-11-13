<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('two_factor_recovery_codes')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $stored = (string) $user->two_factor_recovery_codes;
                $codes = json_decode($stored, true);
                if (! is_array($codes)) {
                    try {
                        $codes = json_decode(Fortify::currentEncrypter()->decrypt($stored), true);
                    } catch (Throwable) {
                        $codes = null;
                    }
                }
                if (! is_array($codes) || $codes === [] || collect($codes)->every(fn ($code): bool => is_string($code) && str_starts_with($code, '$2y$'))) {
                    continue;
                }
                $hashes = array_map(static fn (string $code): string => Hash::make($code), array_values(array_filter($codes, 'is_string')));
                DB::table('users')->where('id', $user->id)->update(['two_factor_recovery_codes' => json_encode($hashes, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    public function down(): void
    {
        // Plain recovery codes cannot be safely reconstructed from one-way hashes.
    }
};
