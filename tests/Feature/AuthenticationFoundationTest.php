<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TwoFactorEmailRecoveryCode;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_tenant_owner_association(): void
    {
        $this->post('/register', [
            'name' => 'Ana Técnica', 'email' => 'ana@example.com',
            'password' => 'Senha-Forte-123!', 'password_confirmation' => 'Senha-Forte-123!',
        ])->assertRedirect();

        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('owner', $user->tenants->first()->pivot->role);
    }

    public function test_operational_routes_require_email_and_two_factor(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/dashboard')->assertRedirect();
        $user->markEmailAsVerified();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active']);
        $this->actingAs($user)->get('/dashboard')->assertRedirectToRoute('two-factor.setup');
    }

    public function test_diagnostic_tenant_context_endpoint_is_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tenant/context')->assertNotFound();
    }

    public function test_two_factor_can_be_disabled_with_a_recovery_code(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['backup-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->post('/two-factor/disable', [
            'current_password' => 'password',
            'code' => 'backup-code',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    }

    public function test_two_factor_setup_requires_current_password_before_generating_secret(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/two-factor/setup')->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'two_factor_secret' => null]);
    }

    public function test_two_factor_settings_route_renders_the_setup_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/security/two-factor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/TwoFactorSetup')
                ->where('twoFactor.configured', false));
    }

    public function test_email_verification_screen_shows_the_authenticated_address(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ana@example.com']);

        $this->actingAs($user)
            ->get('/email/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', 'ana@example.com'));
    }

    public function test_forbidden_web_response_renders_guidance_with_a_forbidden_status(): void
    {
        Route::get('/_test/forbidden', fn () => abort(403));

        $this->get('/_test/forbidden')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Errors/Forbidden'));
    }

    public function test_two_factor_email_recovery_shows_only_a_masked_destination(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['backup-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->withSession(['login.id' => $user->id])
            ->post('/two-factor-challenge/email-code')
            ->assertRedirect()
            ->assertSessionHas('status', 'two-factor-recovery-sent')
            ->assertSessionHas('two_factor_email_recovery', fn (array $recovery): bool => $recovery['masked_email'] === 'a***@example.com');

        Notification::assertSentTo($user, TwoFactorEmailRecoveryCode::class);
    }

    public function test_invalid_email_recovery_code_returns_an_inline_validation_error(): void
    {
        $user = User::factory()->create();

        $this->withSession(['two_factor_email_recovery' => [
            'hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10)->timestamp,
            'user_id' => $user->id,
            'masked_email' => 'u***@example.com',
        ]])->from('/two-factor-challenge')->post('/two-factor-challenge/email-code/verify', ['code' => '000000'])
            ->assertRedirect('/two-factor-challenge')
            ->assertSessionHasErrors('code');
    }

    public function test_two_factor_setup_and_confirmation_require_password_and_code_and_keep_secret_encrypted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/two-factor/setup', ['current_password' => 'password'])->assertRedirect();
        $user->refresh();
        $encrypted = (string) $user->two_factor_secret;
        $secret = Fortify::currentEncrypter()->decrypt($encrypted);
        $this->assertNotSame($encrypted, $secret);
        $storedCodes = json_decode((string) $user->two_factor_recovery_codes, true);
        $this->assertIsArray($storedCodes);
        $this->assertCount(8, $storedCodes);
        $this->assertTrue(collect($storedCodes)->every(fn (string $hash): bool => str_starts_with($hash, '$2y$')));
        $this->assertThrows(fn () => Fortify::currentEncrypter()->decrypt((string) $user->two_factor_recovery_codes), DecryptException::class);

        $provider = \Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')->once()->with($secret, '123456')->andReturnTrue();
        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);

        $this->actingAs($user)->post('/two-factor/confirm', ['code' => '123456'])->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_recovery_codes_require_current_password_and_previous_totp_or_recovery_code(): void
    {
        $user = User::factory()->create();
        $user->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['old-recovery-code'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->post('/two-factor/recovery-codes', ['current_password' => 'password'])
            ->assertSessionHasErrors('code');
        $this->actingAs($user)->post('/two-factor/recovery-codes', ['current_password' => 'wrong', 'code' => 'old-recovery-code'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post('/two-factor/recovery-codes', ['current_password' => 'password', 'code' => 'old-recovery-code'])
            ->assertRedirect()
            ->assertSessionHas('two_factor_recovery_codes');
        $this->assertNotContains('old-recovery-code', $user->fresh()->recoveryCodes());
    }

    public function test_profile_update_does_not_change_login_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->patch('/profile', ['name' => 'Nome atualizado'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nome atualizado', 'email' => 'old@example.com']);
    }

    public function test_profile_page_and_private_photo_upload_use_private_disk_and_validate_files(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
        $this->actingAs($user)->patch('/profile', ['profile_photo' => UploadedFile::fake()->image('avatar.jpg')])->assertRedirect();

        $user->refresh();
        Storage::disk('private')->assertExists($user->profile_photo_path);
        $this->assertStringStartsWith('profile-photos/', $user->profile_photo_path);
        $this->actingAs($user)->patch('/profile', ['profile_photo' => UploadedFile::fake()->create('avatar.exe', 100, 'application/x-msdownload')])->assertSessionHasErrors('profile_photo');
    }

    public function test_email_change_keeps_old_email_until_signed_confirmation(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($user)->post('/profile/email', ['email' => 'new@example.com'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'old@example.com', 'pending_email' => 'new@example.com']);
    }
}
