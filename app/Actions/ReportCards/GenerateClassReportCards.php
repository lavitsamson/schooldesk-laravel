<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Jobs\GenerateReportCard;
use App\Models\Enrollment;
use App\Models\ReportCardBatch;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

/**
 * Queues a report card for everyone in the class during the term: one job per student in a
 * Laravel job batch on the "reports" queue (Horizon). Drafts are recalculated, approved and
 * published cards are kept. One failing student does not stop the others (allowFailures).
 */
final class GenerateClassReportCards
{
    public const QUEUE = 'reports';

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(User $user, SchoolClass $class, Term $term): ReportCardBatch
    {
        $running = ReportCardBatch::query()
            ->where('class_id', $class->id)
            ->where('term_id', $term->id)
            ->latest('id')
            ->first();

        if ($running !== null && ! $running->isFinished()) {
            throw ValidationException::withMessages(['generate' => 'Report cards for this class are already being generated.']);
        }

        $studentIds = Enrollment::query()
            ->where('class_id', $class->id)
            ->activeBetween($term->starts_on, $term->ends_on)
            ->pluck('student_id')
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            throw ValidationException::withMessages(['generate' => 'No students are enrolled in this class in this term.']);
        }

        $schoolId = $this->currentSchool->id();
        $record = ReportCardBatch::query()->create([
            'class_id' => $class->id,
            'term_id' => $term->id,
            'user_id' => $user->id,
            'total' => $studentIds->count(),
        ]);

        $batch = Bus::batch($studentIds->map(fn (int $studentId): GenerateReportCard => new GenerateReportCard($schoolId, $class->id, $term->id, $studentId))->all())
            ->name("Report cards {$class->displayName()} · {$term->name} (school {$schoolId})")
            ->allowFailures()
            ->onQueue(self::QUEUE)
            ->dispatch();

        $record->update(['job_batch_id' => $batch->id]);

        return $record;
    }
}
