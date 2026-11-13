<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicQuoteController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ServiceBillingController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceOperationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TenantOnboardingController;
use App\Http\Controllers\TwoFactorAuthenticationController;
use App\Http\Controllers\TwoFactorEmailRecoveryController;
use App\Services\Dashboard\DashboardService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/perfil/confirmar-email/{user}/{token}', [ProfileController::class, 'confirmEmailChange'])
    ->middleware('signed')->name('profile.email-change.verify');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile/photo', function (Request $request): BinaryFileResponse {
        abort_unless($request->user()?->profile_photo_path, 404);

        return response()->file(storage_path('app/private/'.$request->user()->profile_photo_path), [
            'Cache-Control' => 'private, no-store',
        ]);
    })->name('profile.photo');
    Route::get('/email/verify', fn (Request $request) => Inertia::render('Auth/VerifyEmail', [
        'email' => $request->user()?->email,
    ]))->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request): RedirectResponse {
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('two-factor.setup');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::get('/two-factor/setup', [TwoFactorAuthenticationController::class, 'show'])->middleware('verified')->name('two-factor.setup');
    Route::get('/settings/security/two-factor', [TwoFactorAuthenticationController::class, 'show'])->middleware('verified')->name('settings.security.two-factor');
    Route::post('/two-factor/setup', [TwoFactorAuthenticationController::class, 'enable'])->middleware('verified');
    Route::post('/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->middleware('verified')->name('two-factor.confirm');
    Route::post('/two-factor/recovery-codes', [TwoFactorAuthenticationController::class, 'recoveryCodes'])->middleware('verified')->name('two-factor.recovery-codes');
    Route::post('/two-factor/disable', [TwoFactorAuthenticationController::class, 'disable'])->middleware('verified')->name('two-factor.disable');
    Route::post('/email/verification-notification', function (Request $request): RedirectResponse {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware('throttle:6,1')->name('verification.send');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('verified')->name('profile.update');
    Route::get('/profile', [ProfileController::class, 'show'])->middleware('verified')->name('profile.show');
    Route::post('/profile/email', [ProfileController::class, 'requestEmailChange'])->middleware('verified')->name('profile.email-change.request');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('verified')->name('profile.destroy');
});

Route::middleware('throttle:two-factor')->group(function (): void {
    Route::post('/two-factor-challenge/email-code', [TwoFactorEmailRecoveryController::class, 'send'])->name('two-factor.email-recovery.send');
    Route::post('/two-factor-challenge/email-code/verify', [TwoFactorEmailRecoveryController::class, 'verify'])->name('two-factor.email-recovery.verify');
});

Route::middleware(['auth', 'verified', 'two-factor.configured', 'tenant'])->group(function (): void {
    Route::get('/dashboard', function (Request $request, DashboardService $dashboard, TenantContext $context) {
        return Inertia::render('Dashboard', $dashboard->build($context->get()));
    })->name('dashboard');
    Route::resource('clients', ClientController::class)->only(['index', 'create', 'show', 'edit', 'store', 'update', 'destroy'])->withTrashed(['show']);
    Route::resource('catalog-items', CatalogItemController::class)->parameters(['catalog-items' => 'catalogItem'])->only(['index', 'create', 'edit', 'store', 'update', 'destroy']);
    Route::get('/catalog', [CatalogItemController::class, 'index'])->name('catalog.index');
    Route::post('/catalog', [CatalogItemController::class, 'store'])->name('catalog.store');
    Route::get('/catalog/{catalogItem}/edit', [CatalogItemController::class, 'edit'])->name('catalog.edit');
    Route::get('/catalog/{catalogItem}', [CatalogItemController::class, 'show'])->name('catalog.show');
    Route::patch('/catalog/{catalogItem}', [CatalogItemController::class, 'update'])->name('catalog.update');
    Route::resource('quotes', QuoteController::class)->only(['index', 'create', 'show', 'edit', 'store', 'update']);
    Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
    Route::get('/quotes/{quote}/download', [QuoteController::class, 'download'])->name('quotes.download');
    Route::post('/quotes/{quote}/public-link', [QuoteController::class, 'publicLink'])->name('quotes.public-link');
    Route::post('/quotes/{quote}/convert', [ServiceController::class, 'convert'])->name('quotes.convert');
    Route::get('/quotes/{quote}/versions', [QuoteController::class, 'versions'])->name('quotes.versions');
    Route::post('/quotes/{quote}/revoke-link', [QuoteController::class, 'revoke'])->name('quotes.revoke-link');
    Route::post('/quotes/{quote}/resend-link', [QuoteController::class, 'resend'])->name('quotes.resend-link');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::get('/services/{service}/billing', [ServiceBillingController::class, 'show'])->name('services.billing.show');
    Route::post('/services/{service}/billing', [ServiceBillingController::class, 'store'])->name('services.billing.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::patch('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
    Route::patch('/services/{service}/status', [ServiceController::class, 'status'])->name('services.status');
    Route::post('/services/{service}/attachments', [AttachmentController::class, 'store'])->name('services.attachments.store');
    Route::post('/quotes/{quote}/attachments', [AttachmentController::class, 'storeQuote'])->name('quotes.attachments.store');
    Route::get('/attachments/{attachment}/view', [AttachmentController::class, 'view'])->name('attachments.view');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::post('/services/{service}/tasks', [ServiceOperationController::class, 'task'])->name('services.tasks.store');
    Route::patch('/services/{service}/tasks/{task}/status', [ServiceOperationController::class, 'status'])->name('services.tasks.status');
    Route::post('/services/{service}/notes', [ServiceOperationController::class, 'note'])->name('services.notes.store');
    Route::post('/services/{service}/occurrences', [ServiceOperationController::class, 'occurrence'])->name('services.occurrences.store');
    Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
    Route::post('/receivables/{receivable}/payments', [ReceivableController::class, 'settle'])->name('receivables.settle');
    Route::post('/receivables/{receivable}/cancel', [ReceivableController::class, 'cancel'])->name('receivables.cancel');
    Route::get('/receivables/export', [ReceivableController::class, 'export'])->name('receivables.export');
    Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/plan', [BillingController::class, 'change'])->name('billing.change');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    Route::get('/activity', [ReportsController::class, 'activity'])->name('activity.index');
    Route::get('/settings/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/settings/team/invitations', [TeamController::class, 'invite'])->name('team.invite');
    Route::post('/settings/team/members/{user}/remove', [TeamController::class, 'remove'])->name('team.remove');
});
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/onboarding/tenant', [TenantOnboardingController::class, 'show'])->name('onboarding.tenant');
    Route::post('/onboarding/tenant', [TenantOnboardingController::class, 'store'])->name('onboarding.tenant.store');
    Route::post('/tenant/select/{tenant}', [TenantOnboardingController::class, 'select'])->name('tenant.select');
});
Route::middleware(['auth', 'throttle:invitations'])->group(function (): void {
    Route::get('/invitations/accept', [TeamController::class, 'showAccept'])->name('invitations.accept.show');
    Route::post('/invitations/accept', [TeamController::class, 'accept'])->name('invitations.accept');
});
Route::post('/billing/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');

Route::middleware('public-quote')->prefix('/public/quotes/{token}')->group(function (): void {
    Route::get('/', [PublicQuoteController::class, 'show'])->name('public.quotes.show');
    Route::get('/attachments/{attachment}', [PublicQuoteController::class, 'attachment'])->name('public.quotes.attachments.show');
    Route::post('/approve', [PublicQuoteController::class, 'approve'])->name('public.quotes.approve');
    Route::post('/reject', [PublicQuoteController::class, 'reject'])->name('public.quotes.reject');
});
