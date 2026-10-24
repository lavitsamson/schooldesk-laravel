<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The classes of the term's year with the subjects the user enters marks for (all of them
 * for school admins, their own for teachers) and how many assessments each has in the term.
 * Class teachers also see their class without own subjects, for its results.
 */
final class ListGradebooks
{
    /**
     * @return array<int, array{id: int, name: string, is_class_teacher: bool, can_view_results: bool, subjects: array<int, array{id: int, name: string, code: string, teacher: string|null, assessments_count: int}>}>
     */
    public function handle(User $user, Term $term): array
    {
        $all = $user->can('viewAllClasses', Assessment::class);

        $classes = SchoolClass::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->when(! $all, fn (Builder $q) => $q->taughtBy($user))
            ->with(['teachingAssignments' => fn ($q) => $q
                ->when(! $all, fn ($q) => $q->where('teacher_id', $user->id))
                ->with(['subject', 'teacher'])])
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        $counts = Assessment::query()
            ->where('term_id', $term->id)
            ->whereIn('class_id', $classes->modelKeys())
            ->toBase()
            ->selectRaw('class_id, subject_id, count(*) as aggregate')
            ->groupBy('class_id', 'subject_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->class_id}-{$row->subject_id}" => (int) $row->aggregate]);

        return $classes->map(fn (SchoolClass $class): array => [
            'id' => $class->id,
            'name' => $class->displayName(),
            'is_class_teacher' => $class->class_teacher_id === $user->id,
            'can_view_results' => $user->can('viewResults', [Grade::class, $class]),
            'subjects' => $class->teachingAssignments
                ->sortBy(fn (TeachingAssignment $a): string => mb_strtolower($a->subject->name))
                ->map(fn (TeachingAssignment $a): array => [
                    'id' => $a->subject->id,
                    'name' => $a->subject->name,
                    'code' => $a->subject->code,
                    'teacher' => $a->teacher->name,
                    'assessments_count' => (int) $counts->get("{$class->id}-{$a->subject_id}", 0),
                ])
                ->values()
                ->all(),
        ])->values()->all();
    }
}
