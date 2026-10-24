<?php

declare(strict_types=1);

namespace App\Actions\Students;

use App\Http\Requests\Students\ListStudentsRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The student list: search, filters and sorting, paginated on the server. The class shown and
 * filtered on is the student's class in the current academic year.
 */
final class ListStudents
{
    public const PER_PAGE = 25;

    /**
     * @param  array{search: string, class_id: int|null, status: string, sort: string, direction: string}  $filters
     * @return LengthAwarePaginator<int, Student>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $direction = $filters['direction'] === 'desc' ? 'desc' : 'asc';

        $query = Student::query()
            ->with('currentEnrollment.schoolClass')
            ->when($filters['search'] !== '', fn (Builder $q) => $q->search($filters['search']))
            ->when($filters['status'] !== ListStudentsRequest::ALL, fn (Builder $q) => $q->where('status', $filters['status']))
            ->when($filters['class_id'] !== null, fn (Builder $q) => $q->whereHas(
                'currentEnrollment',
                fn (Builder $e) => $e->where('class_id', $filters['class_id']),
            ));

        match ($filters['sort']) {
            'admission_no' => $query->orderBy('admission_no', $direction),
            'class' => $query->orderBy($this->currentClassName(), $direction)->orderBy('last_name')->orderBy('first_name'),
            default => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
        };

        return $query->orderBy('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * "Grade 7 A" of the student's current enrolment, for sorting by class.
     *
     * @return Builder<Enrollment>
     */
    private function currentClassName(): Builder
    {
        return Enrollment::query()
            ->join('classes', 'classes.id', '=', 'enrollments.class_id')
            ->whereColumn('enrollments.student_id', 'students.id')
            ->whereIn('enrollments.academic_year_id', AcademicYear::query()->current()->select('id'))
            ->selectRaw("concat(classes.name, ' ', coalesce(classes.section, ''))")
            ->limit(1);
    }
}
