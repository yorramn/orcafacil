<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** @var list<string> */
    private array $plainRecoveryCodes = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username', 'phone', 'profile_photo_path',
        'email_bill_notifications_enabled',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email' => 'string',
            'two_factor_confirmed_at' => 'datetime',
            'email_bill_notifications_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot(['role', 'status', 'joined_at'])->withTimestamps();
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path ? route('profile.photo') : null;
    }

    /** @return list<string> */
    public function recoveryCodes(): array
    {
        if ($this->plainRecoveryCodes !== []) {
            return $this->plainRecoveryCodes;
        }

        $stored = $this->two_factor_recovery_codes;
        if (! is_string($stored) || $stored === '') {
            return [];
        }

        $codes = json_decode($stored, true);
        if (is_array($codes) && ($codes === [] || collect($codes)->every(fn ($code): bool => is_string($code) && str_starts_with($code, '$2y$')))) {
            $candidate = app()->bound('request') ? (string) request()->input('recovery_code', request()->input('code', '')) : '';
            if ($candidate !== '' && collect($codes)->contains(fn (string $hash): bool => Hash::check($candidate, $hash))) {
                return [$candidate];
            }

            return [];
        }

        try {
            $legacy = json_decode(Fortify::currentEncrypter()->decrypt($stored), true);
        } catch (\Throwable) {
            return [];
        }
        if (! is_array($legacy)) {
            return [];
        }

        $this->storeRecoveryCodes(array_values(array_filter($legacy, 'is_string')));

        return $this->plainRecoveryCodes;
    }

    /** @param list<string> $codes */
    public function storeRecoveryCodes(array $codes): void
    {
        $this->plainRecoveryCodes = array_values($codes);
        $hashes = array_map(static fn (string $code): string => Hash::make($code), $this->plainRecoveryCodes);
        $this->forceFill(['two_factor_recovery_codes' => json_encode($hashes, JSON_THROW_ON_ERROR)])->save();
    }

    public function replaceRecoveryCode($code): void
    {
        $stored = json_decode((string) $this->two_factor_recovery_codes, true);
        if (! is_array($stored)) {
            return;
        }
        $remaining = array_values(array_filter($stored, fn ($hash): bool => ! is_string($hash) || ! Hash::check((string) $code, $hash)));
        $this->forceFill(['two_factor_recovery_codes' => json_encode($remaining, JSON_THROW_ON_ERROR)])->save();
    }
}
