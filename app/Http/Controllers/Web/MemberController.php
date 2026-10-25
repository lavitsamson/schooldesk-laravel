<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Members\InviteMember;
use App\Actions\Members\RemoveMember;
use App\Actions\Members\ResendInvitation;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\InviteMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Membership;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Membership::class);

        $members = $this->currentSchool->get()
            ->memberships()
            ->with('user.roles')
            ->get()
            ->sortBy(fn (Membership $m): string => $m->status->value.'|'.mb_strtolower($m->user->name))
            ->values();

        return Inertia::render('Members/Index', [
            'members' => MemberResource::collection($members)->resolve(),
            'roles' => array_map(fn (Role $r): array => ['value' => $r->value, 'label' => $r->label()], Role::cases()),
            'invitationExpiresAfterDays' => (int) config('schooldesk.invitations.expire_after_days'),
        ]);
    }

    public function store(InviteMemberRequest $request, InviteMember $action): RedirectResponse
    {
        $action->handle(
            $this->currentSchool->get(),
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->role(),
        );

        return back()->with('status', __('Invitation sent.'));
    }

    public function resend(Membership $membership, ResendInvitation $action): RedirectResponse
    {
        Gate::authorize('resend', $membership);

        $action->handle($membership);

        return back()->with('status', __('Invitation sent again.'));
    }

    public function destroy(Membership $membership, RemoveMember $action): RedirectResponse
    {
        Gate::authorize('delete', $membership);

        $action->handle($membership);

        return back()->with('status', __('Member removed.'));
    }
}
