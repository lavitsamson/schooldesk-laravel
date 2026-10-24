<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything the promotion screen shows before anything is changed: each class of this year
 * with its active students, a suggested target class next year, and which students are
 * already enrolled next year (they are skipped).
 */
final class PreviewPromotion
{
    public function __construct(private readonly SuggestPromotionTargets $suggest) {}

    /**
     * @return array{
     *     source_classes: array<int, array{id: int, name: string, suggested_target: int|string|null, students: array<int, array{id: int, name: string, admission_no: string, already_enrolled: bool}>}>,
     *     target_classes: array<int, array{id: int, name: string}>,
     * }
     */
    public function handle(AcademicYear $from, AcademicYear $to): array
    {
        $sources = $this->classes($from)->load([
            'enrollments' => fn ($q) => $q->whereNull('left_on')->with('student'),
        ]);
        $targets = $this->classes($to);

        $alreadyEnrolled = Enrollment::query()
            ->where('academic_year_id', $to->id)
            ->pluck('student_id')
            ->flip();

        $suggestions = $this->suggest->handle(
            $sources->map(fn (SchoolClass $c): array => $c->only(['id', 'name', 'section']))->values()->all(),
            $targets->map(fn (SchoolClass $c): array => $c->only(['id', 'name', 'section']))->values()->all(),
        );

        return [
            'source_classes' => $sources->map(fn (SchoolClass $class): array => [
                'id' => $class->id,
                'name' => $class->displayName(),
                'suggested_target' => $suggestions[$class->id] ?? null,
                'students' => $class->enrollments
                    ->filter(fn (Enrollment $e): bool => $e->student->status === StudentStatus::Active)
                    ->sortBy(fn (Enrollment $e): string => mb_strtolower($e->student->last_name.' '.$e->student->first_name))
                    ->map(fn (Enrollment $e): array => [
                        'id' => $e->student->id,
                        'name' => $e->student->fullName(),
                        'admission_no' => $e->student->admission_no,
                        'already_enrolled' => $alreadyEnrolled->has($e->student_id),
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
            'target_classes' => $targets
                ->map(fn (SchoolClass $class): array => ['id' => $class->id, 'name' => $class->displayName()])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, SchoolClass>
     */
    private function classes(AcademicYear $year): Collection
    {
        return SchoolClass::query()
            ->where('academic_year_id', $year->id)
            ->orderBy('name')
            ->orderBy('section')
            ->get();
    }
}
