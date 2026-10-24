<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AssessmentType;
use App\Enums\Locale;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Assessments for every class-subject in the current term, with marks for those already held
 * (before today): two tests and a project spread over the term, and an end-of-term exam that
 * is still to come. Entered by the subject teacher.
 *
 * Each student has an ability, nudged per subject, so results differ between students and
 * the grade scale shows its full range. A few are absent from a test (no marks, a remark).
 * Deterministic per school, and bulk-inserted like DemoRosterSeeder.
 */
class DemoGradesSeeder extends Seeder
{
    /**
     * name per locale, type, max marks, weeks after the term starts (null: a week before it ends).
     */
    private const PLAN = [
        [['en' => 'Test 1', 'de' => 'Klassenarbeit 1'], AssessmentType::Test, 20, 3],
        [['en' => 'Project', 'de' => 'Projektarbeit'], AssessmentType::Assignment, 10, 6],
        [['en' => 'Test 2', 'de' => 'Klassenarbeit 2'], AssessmentType::Test, 20, 9],
        [['en' => 'End-of-term exam', 'de' => 'Abschlussprüfung'], AssessmentType::Exam, 100, null],
    ];

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function seedSchool(School $school): void
    {
        $random = new Randomizer(new Mt19937(crc32($school->slug.'-grades')));
        $locale = $school->locale === Locale::German ? 'de' : 'en';

        $this->currentSchool->run($school, function (School $school) use ($random, $locale): void {
            $year = AcademicYear::query()->current()->with('terms')->firstOrFail();
            $today = $school->today();
            /** @var Term $term */
            $term = $year->defaultTerm($today);
            $now = now();

            $assignments = TeachingAssignment::query()
                ->whereIn('class_id', $year->classes()->select('id'))
                ->orderBy('class_id')
                ->orderBy('subject_id')
                ->get(['class_id', 'subject_id', 'teacher_id']);

            $rows = [];
            foreach ($assignments as $index => $assignment) {
                foreach (self::PLAN as [$names, $type, $maxMarks, $weeks]) {
                    // Spread the class-subjects over the week, so not every test is on one day.
                    $heldOn = $weeks === null
                        ? $term->ends_on->subDays(7 + $index % 5)
                        : $term->starts_on->addWeeks($weeks)->addDays($index % 5);

                    $rows[] = [
                        'school_id' => $school->id,
                        'class_id' => $assignment->class_id,
                        'subject_id' => $assignment->subject_id,
                        'term_id' => $term->id,
                        'name' => $names[$locale],
                        'type' => $type->value,
                        'max_marks' => $maxMarks,
                        'weight' => $type->defaultWeight(),
                        'held_on' => $this->weekday($heldOn)->toDateString(),
                        'created_by' => $assignment->teacher_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('assessments')->insert($chunk);
            }

            $this->seedMarks($school, $term, $today, $random, $locale);
        });
    }

    private function seedMarks(School $school, Term $term, CarbonImmutable $today, Randomizer $random, string $locale): void
    {
        $students = Enrollment::query()->where('academic_year_id', $term->academic_year_id)->orderBy('student_id')->get(['student_id', 'class_id']);
        $byClass = $students->groupBy('class_id');

        // Ability per student (45–95 %), nudged per subject below.
        $ability = [];
        foreach ($students as $enrollment) {
            $ability[$enrollment->student_id] = $random->getInt(45, 95);
        }

        $assessments = DB::table('assessments')
            ->where('school_id', $school->id)
            ->where('term_id', $term->id)
            ->where('held_on', '<', $today->toDateString())
            ->orderBy('id')
            ->get(['id', 'class_id', 'subject_id', 'max_marks', 'held_on', 'created_by']);

        $nudge = [];
        $rows = [];

        foreach ($assessments as $assessment) {
            $enteredAt = CarbonImmutable::parse($assessment->held_on.' 16:00', $school->timezone)->addDays(2)->utc()->min(now());

            foreach ($byClass->get($assessment->class_id, collect()) as $enrollment) {
                $key = $enrollment->student_id.'-'.$assessment->subject_id;
                $nudge[$key] ??= $random->getInt(-10, 10);
                $absent = $random->getInt(1, 100) <= 3;

                $percent = max(0, min(100, $ability[$enrollment->student_id] + $nudge[$key] + $random->getInt(-8, 8)));
                // Half marks out of 20 or less, whole marks above.
                $step = $assessment->max_marks <= 20 ? 0.5 : 1;
                $marks = round($percent / 100 * $assessment->max_marks / $step) * $step;

                $rows[] = [
                    'school_id' => $school->id,
                    'assessment_id' => $assessment->id,
                    'student_id' => $enrollment->student_id,
                    'marks' => $absent ? null : number_format($marks, 2, '.', ''),
                    'remarks' => $absent ? ($locale === 'de' ? 'Gefehlt' : 'Absent') : null,
                    'entered_by' => $assessment->created_by,
                    'created_at' => $enteredAt,
                    'updated_at' => $enteredAt,
                ];

                if (count($rows) === 1000) {
                    DB::table('grades')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('grades')->insert($rows);
        }
    }

    private function weekday(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
    }
}
