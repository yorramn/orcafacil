<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Profile', [
            'user' => $user->only(['id', 'name', 'username', 'phone', 'email', 'email_bill_notifications_enabled']),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('profile_photo');
        unset($data['password_confirmation']);
        if (($data['password'] ?? null) === null || $data['password'] === '') {
            unset($data['password']);
        }
        $newPath = null;
        if ($request->hasFile('profile_photo')) {
            $newPath = $request->file('profile_photo')->store('profile-photos', config('filesystems.private_disk'));
            $data['profile_photo_path'] = $newPath;
        }

        try {
            DB::transaction(fn () => $user->update($data));
        } catch (\Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk(config('filesystems.private_disk'))->delete($newPath);
            }
            throw $exception;
        }

        return back()->with('status', 'profile-updated')->with('success', 'Perfil editado com sucesso!');
    }

    public function requestEmailChange(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255', 'lowercase', 'unique:users,email', 'unique:users,pending_email']]);
        abort_if($validated['email'] === $request->user()->email, 422, 'The new email must be different.');
        $user = $request->user();
        $user->forceFill(['pending_email' => $validated['email']])->save();
        $token = hash('sha256', $validated['email']);
        $url = URL::temporarySignedRoute('profile.email-change.verify', now()->addHour(), ['user' => $user->getKey(), 'token' => $token]);
        $user->notify(new ConfirmEmailChange($url));

        return back()->with('status', 'email-change-sent');
    }

    public function confirmEmailChange(Request $request, User $user, string $token): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless($request->user()?->is($user) || hash_equals(hash('sha256', (string) $user->pending_email), $token), 403);
        abort_unless($user->pending_email !== null && hash_equals(hash('sha256', $user->pending_email), $token), 403);
        $user->forceFill(['email' => $user->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'email-changed');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        auth()->logout();
        $request->session()->invalidate();
        $user->delete();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'account-deleted');
    }
}
