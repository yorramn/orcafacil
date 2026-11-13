<?php

namespace App\Http\Controllers;

use App\Actions\Team\AcceptInvitation;
use App\Http\Requests\InviteMemberRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Tenancy\InviteMember;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeamController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $tenant = $context->get();
        $this->authorize('team.view', $tenant);

        $members = $tenant->users()->wherePivot('status', 'active')->orderBy('name')->paginate(15, ['users.id', 'users.name', 'users.email'], 'members_page')->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
        ]);
        $invitations = $tenant->invitations()->whereNull('accepted_at')->latest()->paginate(15, ['id', 'email', 'role', 'expires_at'], 'invitations_page');

        return Inertia::render('Settings/Team', ['members' => $members, 'invitations' => $invitations, 'usage' => ['current' => $tenant->users()->wherePivot('status', 'active')->count(), 'limit' => 5]]);
    }

    public function invite(InviteMemberRequest $request, TenantContext $context, InviteMember $invite): RedirectResponse
    {
        $inv = $invite->handle($context->get(), $request->user(), $request->string('email')->toString(), $request->string('role')->toString());
        logger()->info('team.invitation.created', ['invitation_id' => $inv->id, 'tenant_id' => $context->id()]);

        return back()->with('success', 'Convite criado e enviado.');
    }

    public function accept(Request $request, AcceptInvitation $accept): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);
        $accept->execute($request->string('token')->toString(), $request->user());

        return redirect()->route('dashboard')->with('success', 'Convite aceito.');
    }

    public function showAccept(Request $request): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);
        $invitation = Invitation::query()->with('tenant')->where('token_hash', hash('sha256', $request->string('token')->toString()))->first();
        abort_unless($invitation
            && $invitation->accepted_at === null
            && $invitation->expires_at->isFuture()
            && strcasecmp($invitation->email, (string) $request->user()->email) === 0, 404);

        return response()->json(['data' => [
            'email' => $invitation->email,
            'role' => $invitation->role,
            'tenant' => ['id' => $invitation->tenant_id, 'name' => $invitation->tenant->name],
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'accept_url' => route('invitations.accept'),
            'method' => 'POST',
        ]]);
    }

    public function remove(Request $request, TenantContext $context, User $user): RedirectResponse
    {
        $tenant = $context->get();
        $this->authorize('team.remove', [$tenant, $user]);
        $tenant->users()->updateExistingPivot($user, ['status' => 'removed']);

        return back()->with('success', 'Membro removido.');
    }
}
