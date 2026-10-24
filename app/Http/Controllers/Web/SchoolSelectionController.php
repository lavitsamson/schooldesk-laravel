<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetCurrentSchool;
use App\Models\School;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolSelectionController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('Schools/Select', [
            'schools' => $user->activeSchools()
                ->orderBy('name')
                ->get(['schools.id', 'schools.name'])
                ->map(fn (School $school): array => ['id' => $school->id, 'name' => $school->name]),
        ]);
    }

    public function switch(Request $request, #[CurrentUser] User $user, School $school): RedirectResponse
    {
        // Not a member: 404, so the existence of other schools is not revealed.
        abort_unless($user->activeSchools()->whereKey($school->id)->exists(), 404);

        $request->session()->put(SetCurrentSchool::SESSION_KEY, $school->id);

        return to_route('dashboard');
    }
}
