<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Enums\ActivityArea;
use App\Jobs\PrepareStudentExport;
use App\Models\Student;
use App\Models\StudentExport;
use App\Models\User;

/**
 * A school admin asks for a student's data export: the ZIP is built by a queued job, and the
 * download link emailed to them. The request is logged in the audit log.
 */
final class RequestStudentExport
{
    public function handle(Student $student, User $user): StudentExport
    {
        $export = StudentExport::query()->create([
            'student_id' => $student->id,
            'user_id' => $user->id,
        ]);

        activity(ActivityArea::Gdpr)
            ->performedOn($student)
            ->causedBy($user)
            ->event('exported')
            ->log('exported');

        PrepareStudentExport::dispatch((int) $export->getAttribute('school_id'), $export->id);

        return $export;
    }
}
