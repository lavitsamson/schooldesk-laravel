<?php

declare(strict_types=1);

namespace App\Actions\Students;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Bulk action: students who left the school during or at the end of the year.
 */
final class MarkStudentsAsLeft
{
    public function __construct(private readonly ChangeStudentStatus $changeStatus) {}

    /**
     * @param  iterable<Student>  $students
     */
    public function handle(iterable $students): int
    {
        return DB::transaction(function () use ($students): int {
            $count = 0;

            foreach ($students as $student) {
                $this->changeStatus->handle($student, StudentStatus::Left);
                $count++;
            }

            return $count;
        });
    }
}
