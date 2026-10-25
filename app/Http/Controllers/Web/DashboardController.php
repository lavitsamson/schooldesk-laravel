<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Family\BuildFamilyOverview;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start page. Parents (and only parents) land on the parent portal's family overview.
 */
class DashboardController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, CurrentSchool $currentSchool, BuildFamilyOverview $overview): Response|RedirectResponse
    {
        $school = $currentSchool->get();

        if (! $school->isSetUp() && $user->can('update', $school)) {
            return to_route('setup');
        }

        if ($school->isSetUp() && $user->hasRole(Role::Parent) && ! $user->hasAnyRole([Role::SchoolAdmin, Role::Teacher])) {
            return Inertia::render('Family/Home', [
                'children' => $overview->handle($user),
            ]);
        }

        return Inertia::render('Dashboard');
    }
}
