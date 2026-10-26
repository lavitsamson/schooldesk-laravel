<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Gdpr\UpdateAttendanceNoteRetention;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gdpr\UpdateDataProtectionRequest;
use App\Models\Student;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The school's data protection page: retention of attendance notes, and how exports and
 * erasure work (they are started from a student's page).
 */
class DataProtectionController extends Controller
{
    public function edit(CurrentSchool $currentSchool): Response
    {
        $school = $currentSchool->get();
        Gate::authorize('manageDataProtection', $school);

        return Inertia::render('DataProtection/Edit', [
            'attendanceNoteRetentionMonths' => $school->attendanceNoteRetentionMonths(),
            'retentionOptions' => config('schooldesk.gdpr.attendance_note_retention_months'),
            'exportLinkHours' => (int) config('schooldesk.gdpr.export_link_hours'),
            'anonymisedStudents' => Student::query()->whereNotNull('anonymised_at')->count(),
        ]);
    }

    public function update(UpdateDataProtectionRequest $request, CurrentSchool $currentSchool, UpdateAttendanceNoteRetention $action): RedirectResponse
    {
        $action->handle($currentSchool->get(), $request->retentionMonths());

        return back()->with('status', __('Data protection settings saved.'));
    }
}
