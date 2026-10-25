<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\ReportCards\SaveReportCardSnapshot;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Support\CurrentSchool;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One student's report card in a "generate for the class" batch. Carries ids only and sets
 * the school context itself, like every queued job (ADR 0001).
 */
final class GenerateReportCard implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $classId,
        public readonly int $termId,
        public readonly int $studentId,
    ) {}

    public function handle(CurrentSchool $currentSchool, SaveReportCardSnapshot $action): void
    {
        if ($this->batch()?->cancelled() === true) {
            return;
        }

        $currentSchool->run($this->schoolId, function () use ($action): void {
            $student = Student::query()->find($this->studentId);

            // Removed from the school since the batch started: nothing to write.
            if ($student === null) {
                return;
            }

            $action->handle(
                $student,
                Term::query()->findOrFail($this->termId),
                SchoolClass::query()->findOrFail($this->classId),
            );
        });
    }
}
