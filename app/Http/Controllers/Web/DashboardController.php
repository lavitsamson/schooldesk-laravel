<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, CurrentSchool $currentSchool): Response|RedirectResponse
    {
        $school = $currentSchool->get();

        if (! $school->isSetUp() && $user->can('update', $school)) {
            return to_route('setup');
        }

        return Inertia::render('Dashboard');
    }
}
