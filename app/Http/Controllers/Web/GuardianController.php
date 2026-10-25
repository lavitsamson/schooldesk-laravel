<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Guardians\InviteGuardian;
use App\Actions\Guardians\ListGuardians;
use App\Actions\Guardians\SaveGuardian;
use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\SaveGuardianRequest;
use App\Http\Resources\GuardianResource;
use App\Models\Guardian;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuardianController extends Controller
{
    public function index(Request $request, ListGuardians $action): Response
    {
        Gate::authorize('viewAny', Guardian::class);

        $search = mb_substr(trim($request->string('search')->toString()), 0, 100);

        return Inertia::render('Guardians/Index', [
            'guardians' => GuardianResource::collection($action->handle($search)),
            'filters' => ['search' => $search],
            'relationships' => EnumOptions::of(GuardianRelationship::class),
        ]);
    }

    public function update(SaveGuardianRequest $request, Guardian $guardian, SaveGuardian $action): RedirectResponse
    {
        /** @var array{name: string, email?: string|null, phone?: string|null, relationship: string} $data */
        $data = $request->validated();
        $action->handle($guardian, $data);

        return back()->with('status', __('Guardian saved.'));
    }

    public function destroy(Guardian $guardian): RedirectResponse
    {
        Gate::authorize('delete', $guardian);

        $guardian->delete();

        return back()->with('status', __(':name deleted.', ['name' => $guardian->name]));
    }

    public function invite(Guardian $guardian, InviteGuardian $action): RedirectResponse
    {
        Gate::authorize('invite', $guardian);

        $sent = $action->handle($guardian);

        return back()->with('status', $sent
            ? __('Invitation to the parent portal sent to :email.', ['email' => $guardian->email])
            : __(':name already has an account here and can now use the parent portal.', ['name' => $guardian->name]));
    }
}
