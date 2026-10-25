<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Members\AcceptInvitation;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetCurrentSchool;
use App\Http\Requests\Invitations\AcceptInvitationRequest;
use App\Models\Membership;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Both routes sit behind the `signed` middleware, so the link cannot be forged or used after
 * it expires. The `sent` parameter must match invited_at: re-sending invalidates old links.
 */
class InvitationController extends Controller
{
    public function show(Request $request, Membership $invitation, CurrentSchool $currentSchool): Response|RedirectResponse
    {
        $this->ensureLatestLink($request, $invitation);

        if ($invitation->isActive()) {
            return to_route('dashboard')->with('status', __('You have already accepted this invitation.'));
        }

        $invitee = $invitation->user;
        $viewer = $request->user();

        $mode = match (true) {
            ! $invitee->hasPassword() => 'register',
            $viewer === null => 'login',
            $invitee->is($viewer) => 'accept',
            default => 'wrong_account',
        };

        if ($mode === 'login') {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        $roles = $currentSchool->run(
            $invitation->school,
            fn (): array => $invitee->getRoleNames()
                ->map(fn (string $name): string => Role::tryFrom($name)?->label() ?? $name)
                ->all(),
        );
        $invitee->unsetRelation('roles');

        return Inertia::render('Invitations/Accept', [
            'mode' => $mode,
            'schoolName' => $invitation->school->name,
            'email' => $invitee->email,
            'name' => $invitee->name,
            'roles' => $roles,
            'acceptUrl' => $request->fullUrl(),
        ]);
    }

    public function accept(AcceptInvitationRequest $request, Membership $invitation, AcceptInvitation $action): RedirectResponse
    {
        $this->ensureLatestLink($request, $invitation);

        if (! $invitation->isActive()) {
            /** @var array{name?: string, password?: string} $account */
            $account = $request->validated();
            $user = $action->handle($invitation, $account);

            if (! $user->is($request->user())) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }

        $request->session()->put(SetCurrentSchool::SESSION_KEY, $invitation->school_id);

        return to_route('dashboard')->with('status', __('Welcome to :name.', ['name' => $invitation->school->name]));
    }

    private function ensureLatestLink(Request $request, Membership $invitation): void
    {
        abort_unless(
            $request->integer('sent') === $invitation->invited_at?->getTimestamp(),
            403,
            __('This invitation link has been replaced by a newer one. Please use the latest email.'),
        );
    }
}
