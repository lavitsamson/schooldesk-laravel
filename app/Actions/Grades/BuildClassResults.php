<?php

declare(strict_types=1);

namespace App\Actions\Grades;

use App\Models\SchoolClass;
use App\Models\Term;

/**
 * A class's term results as a table: students by subjects, with the overall result and
 * attendance rate. Each row is the student's report card calculation (CalculateReportCard),
 * so the table and the report cards always agree.
 */
final class BuildClassResults
{
    public function __construct(private readonly CalculateReportCard $calculate) {}

    /**
     * @return array{
     *     subjects: array<int, array{id: int, name: string, code: string}>,
     *     students: array<int, array{id: int, name: string, admission_no: string, results: array<int, array{percentage: string|null, grade: string|null}>, overall: array{percentage: string|null, grade: string|null}, attendance_rate: float|null}>,
     * }
     */
    public function handle(SchoolClass $class, Term $term): array
    {
        $snapshots = $this->calculate->forClass($class, $term);

        $subjects = [];
        foreach ($snapshots as $snapshot) {
            foreach ($snapshot['subjects'] as $subject) {
                $subjects[$subject['id']] ??= ['id' => $subject['id'], 'name' => $subject['name'], 'code' => $subject['code']];
            }
        }
        usort($subjects, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return [
            'subjects' => $subjects,
            'students' => array_values(array_map(fn (array $snapshot): array => [
                'id' => $snapshot['student']['id'],
                'name' => $snapshot['student']['first_name'].' '.$snapshot['student']['last_name'],
                'admission_no' => $snapshot['student']['admission_no'],
                'results' => collect($snapshot['subjects'])->mapWithKeys(fn (array $s): array => [
                    $s['id'] => ['percentage' => $s['percentage'], 'grade' => $s['grade']],
                ])->all(),
                'overall' => $snapshot['overall'],
                'attendance_rate' => $snapshot['attendance']['rate'],
            ], $snapshots)),
        ];
    }
}
