<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Actions\Grades\CalculateReportCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A child's results in a term so far (CalculateReportCard): per subject the weighted
 * percentage, the grade and the marks of each assessment held. Percentages are decimal
 * strings ("86.25"), exactly as calculated; null where nothing is marked yet.
 *
 * @phpstan-import-type Snapshot from CalculateReportCard
 *
 * @property Snapshot $resource
 */
class ResultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;

        return [
            'student' => ['id' => $report['student']['id'], 'first_name' => $report['student']['first_name']],
            'class' => $report['class'],
            'term' => $report['term'],
            'overall' => $report['overall'],
            'attendance' => $report['attendance'],
            'subjects' => array_map(fn (array $subject): array => [
                'id' => $subject['id'],
                'name' => $subject['name'],
                'code' => $subject['code'],
                'percentage' => $subject['percentage'],
                'grade' => $subject['grade'],
                'assessments' => array_map(fn (array $a): array => [
                    'id' => $a['id'],
                    'name' => $a['name'],
                    'type' => $a['type'],
                    'held_on' => $a['held_on'],
                    'max_marks' => $a['max_marks'],
                    'weight' => $a['weight'],
                    'marks' => $a['marks'],
                ], array_values($subject['assessments'])),
            ], array_values($report['subjects'])),
            'grade_scale' => $report['grade_scale'],
        ];
    }
}
