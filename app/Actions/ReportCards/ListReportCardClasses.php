<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Enums\Permission;
use App\Enums\ReportCardStatus;
use App\Models\Enrollment;
use App\Models\ReportCard;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Models\User;
use stdClass;

/**
 * The classes whose report cards the user writes in a term (all classes for school admins,
 * their own class for class teachers), with how far each class has got. Three queries for
 * all classes together.
 */
final class ListReportCardClasses
{
    /**
     * @return array<int, array{id: int, name: string, class_teacher: string|null, students: int, draft: int, approved: int, published: int}>
     */
    public function handle(User $user, Term $term): array
    {
        $classes = SchoolClass::query()
            ->where('academic_year_id', $term->academic_year_id)
            ->when(! $user->hasPermissionTo(Permission::ManageSchool), fn ($q) => $q->where('class_teacher_id', $user->id))
            ->with('classTeacher:id,name')
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        $classIds = $classes->pluck('id')->all();

        $students = Enrollment::query()
            ->whereIn('class_id', $classIds)
            ->activeBetween($term->starts_on, $term->ends_on)
            ->toBase()
            ->selectRaw('class_id, count(distinct student_id) as aggregate')
            ->groupBy('class_id')
            ->pluck('aggregate', 'class_id');

        $cards = ReportCard::query()
            ->whereIn('class_id', $classIds)
            ->where('term_id', $term->id)
            ->toBase()
            ->selectRaw('class_id, status, count(*) as aggregate')
            ->groupBy('class_id', 'status')
            ->get()
            ->mapToGroups(fn (stdClass $row): array => [(int) $row->class_id => [(string) $row->status => (int) $row->aggregate]]);

        return $classes->map(function (SchoolClass $class) use ($students, $cards): array {
            $counts = collect(array_merge(...$cards->get($class->id, collect())->all()));

            return [
                'id' => $class->id,
                'name' => $class->displayName(),
                'class_teacher' => $class->classTeacher?->name,
                'students' => (int) ($students[$class->id] ?? 0),
                'draft' => (int) $counts->get(ReportCardStatus::Draft->value, 0),
                'approved' => (int) $counts->get(ReportCardStatus::Approved->value, 0),
                'published' => (int) $counts->get(ReportCardStatus::Published->value, 0),
            ];
        })->all();
    }
}
