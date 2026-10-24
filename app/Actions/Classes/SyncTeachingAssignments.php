<?php

declare(strict_types=1);

namespace App\Actions\Classes;

use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Makes a class's subject teachers match the given list. Subjects without a teacher, or not
 * listed, are not taught in the class.
 */
final class SyncTeachingAssignments
{
    /**
     * @param  array<int, array{subject_id: int, teacher_id: int|null}>  $assignments
     */
    public function handle(SchoolClass $class, array $assignments): void
    {
        DB::transaction(function () use ($class, $assignments): void {
            $keep = [];

            foreach ($assignments as $assignment) {
                if ($assignment['teacher_id'] === null) {
                    continue;
                }

                $keep[] = TeachingAssignment::query()->updateOrCreate(
                    ['class_id' => $class->id, 'subject_id' => $assignment['subject_id']],
                    ['teacher_id' => $assignment['teacher_id']],
                )->id;
            }

            TeachingAssignment::query()->where('class_id', $class->id)->whereNotIn('id', $keep)->delete();
        });
    }
}
