<?php

declare(strict_types=1);

namespace App\Actions\Students;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Bulk action: moves (or enrols) students into a class, all or nothing.
 */
final class MoveStudentsToClass
{
    public function __construct(private readonly EnrollStudent $enrollStudent) {}

    /**
     * @param  iterable<Student>  $students
     */
    public function handle(iterable $students, SchoolClass $class): int
    {
        return DB::transaction(function () use ($students, $class): int {
            $count = 0;

            foreach ($students as $student) {
                $this->enrollStudent->handle($student, $class);
                $count++;
            }

            return $count;
        });
    }
}
