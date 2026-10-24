<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Models\Assessment;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves marks and remarks for some students of an assessment, all or nothing. The grid
 * autosaves only the rows that changed, so entries not sent are left alone. A row with
 * neither marks nor a remark is removed: the student is back to "not marked".
 */
final class RecordGrades
{
    /**
     * @param  list<array{student_id: int, marks: string|null, remarks: string|null}>  $entries
     * @return array{saved: int, graded: int}
     */
    public function handle(Assessment $assessment, array $entries, User $enteredBy): array
    {
        return DB::transaction(function () use ($assessment, $entries, $enteredBy): array {
            $existing = Grade::query()
                ->where('assessment_id', $assessment->id)
                ->whereIn('student_id', array_column($entries, 'student_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('student_id');

            foreach ($entries as $entry) {
                /** @var Grade|null $grade */
                $grade = $existing->get($entry['student_id']);

                if ($entry['marks'] === null && $entry['remarks'] === null) {
                    $grade?->delete();

                    continue;
                }

                $grade ??= new Grade(['assessment_id' => $assessment->id, 'student_id' => $entry['student_id']]);
                $grade->fill(['marks' => $entry['marks'], 'remarks' => $entry['remarks']]);

                if ($grade->isDirty()) {
                    $grade->entered_by = $enteredBy->id;
                    $grade->save();
                }
            }

            return [
                'saved' => count($entries),
                'graded' => Grade::query()->where('assessment_id', $assessment->id)->whereNotNull('marks')->count(),
            ];
        });
    }
}
