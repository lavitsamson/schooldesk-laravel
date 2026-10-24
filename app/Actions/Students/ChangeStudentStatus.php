<?php

declare(strict_types=1);

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A student who leaves or graduates keeps their current enrolment as history, closed with a
 * left_on date. Re-activating them reopens it.
 */
final class ChangeStudentStatus
{
    public function handle(Student $student, StudentStatus $status, ?CarbonImmutable $on = null): void
    {
        if ($student->status === $status) {
            return;
        }

        DB::transaction(function () use ($student, $status, $on): void {
            $student->update(['status' => $status]);

            $student->currentEnrollment()->update([
                'left_on' => $status === StudentStatus::Active ? null : ($on ?? CarbonImmutable::today())->toDateString(),
            ]);
        });
    }
}
