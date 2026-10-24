<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Guardians\LinkGuardian;
use App\Actions\Guardians\MakePrimaryGuardian;
use App\Actions\Guardians\SaveGuardian;
use App\Actions\Guardians\UnlinkGuardian;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardians\LinkGuardianRequest;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The guardians of one student: add (new or existing), make primary, unlink.
 */
class StudentGuardianController extends Controller
{
    public function store(
        LinkGuardianRequest $request,
        Student $student,
        SaveGuardian $saveGuardian,
        LinkGuardian $linkGuardian,
    ): RedirectResponse {
        $guardian = DB::transaction(function () use ($request, $student, $saveGuardian, $linkGuardian): Guardian {
            $guardian = $request->input('mode') === 'existing'
                ? $request->existingGuardian() ?? abort(422)
                : $saveGuardian->handle(null, $request->guardianData());

            $linkGuardian->handle($student, $guardian, $request->boolean('is_primary'));

            return $guardian;
        });

        return back()->with('status', "{$guardian->name} added as a guardian.");
    }

    public function update(Student $student, Guardian $guardian, MakePrimaryGuardian $action): RedirectResponse
    {
        Gate::authorize('update', $student);
        $this->ensureLinked($student, $guardian);

        $action->handle($student, $guardian);

        return back()->with('status', "{$guardian->name} is now the primary contact.");
    }

    public function destroy(Student $student, Guardian $guardian, UnlinkGuardian $action): RedirectResponse
    {
        Gate::authorize('update', $student);
        $this->ensureLinked($student, $guardian);

        $action->handle($student, $guardian);

        return back()->with('status', "{$guardian->name} removed from {$student->first_name}'s guardians.");
    }

    private function ensureLinked(Student $student, Guardian $guardian): void
    {
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
    }
}
