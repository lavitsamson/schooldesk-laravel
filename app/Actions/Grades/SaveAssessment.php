<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Models\Assessment;

/**
 * Creates or updates an assessment. A new one arrives with its class, subject, term and
 * author set; the form only changes what it is, how many marks it has and how much it counts.
 */
final class SaveAssessment
{
    /**
     * @param  array{name: string, type: string, held_on: string, max_marks: int|string, weight: string}  $data
     */
    public function handle(Assessment $assessment, array $data): Assessment
    {
        $assessment->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'held_on' => $data['held_on'],
            'max_marks' => (int) $data['max_marks'],
            'weight' => $data['weight'],
        ])->save();

        return $assessment;
    }
}
