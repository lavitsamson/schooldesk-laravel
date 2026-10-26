<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AcademicYears\CreateAcademicYear;
use App\Enums\AssessmentType;
use App\Enums\AttendanceStatus;
use App\Enums\FeeFrequency;
use App\Enums\GradeScalePreset;
use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * The performance dataset (README: "Performance"): 20 schools with 504 students each
 * (10,080 in all), followed back through two earlier academic years, with every school day's
 * attendance of the last two years (about 4 million rows), marks for this year and last, and
 * the invoices and payments of those years.
 *
 *     php artisan db:seed --class=PerformanceSeeder
 *
 * Added beside the demo schools (slugs perf-01 … perf-20), not instead of them. Logins:
 * admin@perf-01.test and teacher1@perf-01.test … teacher8@perf-01.test, password as in the demo.
 * Schools are set up through the setup wizard's actions (DemoSchoolsSeeder) and the current
 * roster through DemoRosterSeeder; the history is bulk-inserted, deterministic per school, and
 * sends no mail: absences count as notified, invoices as reminded.
 */
class PerformanceSeeder extends Seeder
{
    public const SCHOOLS = 20;

    /** 12 classes × 42 = 504 students per school. */
    private const PER_CLASS = 42;

    private const TEACHERS = 8;

    /** Earlier academic years each student is followed back through. */
    private const PAST_YEARS = 2;

    private const TERMS = ['Autumn term', 'Spring term', 'Summer term'];

    private const SUBJECTS = [
        'ENG' => 'English', 'MATH' => 'Mathematics', 'SCI' => 'Science', 'HIST' => 'History',
        'GEO' => 'Geography', 'GER' => 'German', 'FRE' => 'French', 'ART' => 'Art',
        'MUS' => 'Music', 'PE' => 'Physical Education',
    ];

    /** name, type, max marks, weeks after the term starts (null: a week before it ends). */
    private const ASSESSMENTS = [
        ['Test 1', AssessmentType::Test, 20, 3],
        ['Project', AssessmentType::Assignment, 10, 6],
        ['Test 2', AssessmentType::Test, 20, 9],
        ['End-of-term exam', AssessmentType::Exam, 100, null],
    ];

    private const TUITION = 45_000;

    private const DUE_DAYS = 14;

    /** Rows per INSERT statement (well below the placeholder limits of MySQL and PostgreSQL). */
    private const CHUNK = 2000;

    private Randomizer $random;

    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly DemoSchoolsSeeder $schools,
        private readonly DemoRosterSeeder $roster,
        private readonly CreateAcademicYear $createYear,
    ) {}

    public function run(): void
    {
        if (School::query()->where('slug', 'perf-01')->exists()) {
            $this->command->warn('The performance schools exist already: run migrate:fresh --seed first.');

            return;
        }

        $started = microtime(true);

        // Foreign keys are checked by the code that builds the rows; skipping the checks
        // roughly halves the time of the bulk inserts on MySQL.
        Schema::withoutForeignKeyConstraints(function (): void {
            for ($n = 1; $n <= self::SCHOOLS; $n++) {
                $this->seedSchool($n);
                $this->command->info(sprintf('School %d of %d seeded.', $n, self::SCHOOLS));
            }
        });

        $schoolIds = School::query()->where('slug', 'like', 'perf-%')->pluck('id');
        foreach (['students', 'attendance_records', 'grades', 'invoices', 'payments'] as $table) {
            $this->command->line(sprintf('%-20s %s', $table, number_format(DB::table($table)->whereIn('school_id', $schoolIds)->count())));
        }
        $this->command->info(sprintf('Done in %d s.', (int) (microtime(true) - $started)));
    }

    private function seedSchool(int $n): void
    {
        $slug = sprintf('perf-%02d', $n);
        $german = $n % 2 === 0;
        $this->random = new Randomizer(new Mt19937(crc32($slug)));

        $school = $this->schools->school([
            'name' => sprintf('Performance School %02d', $n),
            'slug' => $slug,
            'locale' => $german ? Locale::German : Locale::English,
        ], $german ? GradeScalePreset::German1To6 : GradeScalePreset::LetterAToF, self::TERMS, self::SUBJECTS);

        $office = $this->schools->member($school, "admin@{$slug}.test", sprintf('Admin %02d', $n), Role::SchoolAdmin);
        $teachers = [];
        for ($t = 1; $t <= self::TEACHERS; $t++) {
            $teachers[] = $this->schools->member($school, "teacher{$t}@{$slug}.test", "Teacher {$t}", Role::Teacher);
        }

        $this->roster->seedSchool($school, sprintf('P%02d', $n), self::PER_CLASS, $teachers, 'MATH');

        $this->currentSchool->run($school, function (School $school) use ($office): void {
            $current = AcademicYear::query()->current()->with('terms')->firstOrFail();
            $years = [$current];
            for ($k = 1; $k <= self::PAST_YEARS; $k++) {
                $years[] = $this->pastYear($current, $k);
            }

            $this->attendance($school, $years);
            // Marks and fees for this year and last.
            $this->marks($school, array_slice($years, 0, 2));
            $this->invoices($school, array_slice($years, 0, 2), $office);
        });
    }

    /**
     * The year $k years before the current one, with its classes (every current class $k
     * grades lower, same section and teachers) and the current students' enrolments then.
     */
    private function pastYear(AcademicYear $current, int $k): AcademicYear
    {
        $back = fn (CarbonImmutable $date): string => $date->subYears($k)->toDateString();

        $year = $this->createYear->handle([
            'name' => sprintf('%d/%02d', $current->starts_on->year - $k, ($current->starts_on->year - $k + 1) % 100),
            'starts_on' => $back($current->starts_on),
            'ends_on' => $back($current->ends_on),
            'terms' => array_values($current->terms->sortBy('starts_on')->map(fn (Term $term): array => [
                'name' => $term->name,
                'starts_on' => $back($term->starts_on),
                'ends_on' => $back($term->ends_on),
            ])->all()),
        ]);
        $year->load('terms');

        $classIds = [];
        foreach (SchoolClass::query()->where('academic_year_id', $current->id)->orderBy('id')->get() as $class) {
            $grade = (int) preg_replace('/\D/', '', $class->name);
            $classIds[$class->id] = $year->classes()->create([
                'name' => 'Grade '.($grade - $k),
                'section' => $class->section,
                'class_teacher_id' => $class->class_teacher_id,
            ])->id;
        }

        $now = now();
        $assignments = TeachingAssignment::query()->whereIn('class_id', array_keys($classIds))->get(['class_id', 'subject_id', 'teacher_id']);
        $this->insert('class_subject_teacher', $assignments->map(fn (TeachingAssignment $a): array => [
            'school_id' => $current->school_id,
            'class_id' => $classIds[$a->class_id],
            'subject_id' => $a->subject_id,
            'teacher_id' => $a->teacher_id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        $enrollments = Enrollment::query()->where('academic_year_id', $current->id)->get(['student_id', 'class_id', 'roll_no']);
        $this->insert('enrollments', $enrollments->map(fn (Enrollment $e): array => [
            'school_id' => $current->school_id,
            'student_id' => $e->student_id,
            'class_id' => $classIds[$e->class_id],
            'academic_year_id' => $year->id,
            'roll_no' => $e->roll_no,
            'enrolled_on' => $year->starts_on->toDateString(),
            'left_on' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        return $year;
    }

    /**
     * Every term weekday of the last two years up to yesterday, recorded by the class teacher.
     * Most students are rarely absent, a few often (as in DemoAttendanceSeeder).
     *
     * @param  list<AcademicYear>  $years
     */
    private function attendance(School $school, array $years): void
    {
        $to = $school->today()->subDay();
        $from = $to->subYears(2)->addDay();
        $absenceRate = [];

        foreach ($years as $year) {
            $teachers = SchoolClass::query()->where('academic_year_id', $year->id)->pluck('class_teacher_id', 'id');
            $enrollments = Enrollment::query()->where('academic_year_id', $year->id)->orderBy('id')->get(['student_id', 'class_id']);
            $rows = [];

            foreach ($year->terms as $term) {
                for ($day = $term->starts_on->max($from); $day->lte($term->ends_on->min($to)); $day = $day->addDay()) {
                    if ($day->isWeekend()) {
                        continue;
                    }

                    $recordedAt = CarbonImmutable::parse($day->toDateString().' 08:15', $school->timezone)->utc();

                    foreach ($enrollments as $enrollment) {
                        $absenceRate[$enrollment->student_id] ??= $this->weighted([2 => 85, 7 => 12, 18 => 3]);
                        $status = $this->status($absenceRate[$enrollment->student_id]);

                        $rows[] = [
                            'school_id' => $school->id,
                            'student_id' => $enrollment->student_id,
                            'class_id' => $enrollment->class_id,
                            'date' => $day->toDateString(),
                            'status' => $status->value,
                            'recorded_by' => $teachers[$enrollment->class_id] ?? null,
                            'guardian_notified_at' => $status === AttendanceStatus::Absent ? $recordedAt->addMinutes(10) : null,
                            'created_at' => $recordedAt,
                            'updated_at' => $recordedAt,
                        ];

                        if (count($rows) === self::CHUNK) {
                            DB::table('attendance_records')->insert($rows);
                            $rows = [];
                        }
                    }
                }
            }

            $this->insert('attendance_records', $rows);
        }
    }

    /**
     * Four assessments per class-subject in every term that has started, with marks for those
     * held before today, entered by the subject teacher.
     *
     * @param  list<AcademicYear>  $years
     */
    private function marks(School $school, array $years): void
    {
        $today = $school->today();
        $now = now();
        $ability = [];

        foreach ($years as $year) {
            $assignments = TeachingAssignment::query()
                ->whereIn('class_id', $year->classes()->select('id'))
                ->orderBy('class_id')
                ->orderBy('subject_id')
                ->get(['class_id', 'subject_id', 'teacher_id']);

            $rows = [];
            foreach ($year->terms->filter(fn (Term $term): bool => $term->starts_on->lte($today)) as $term) {
                foreach ($assignments as $index => $assignment) {
                    foreach (self::ASSESSMENTS as [$name, $type, $maxMarks, $weeks]) {
                        $heldOn = $weeks === null
                            ? $term->ends_on->subDays(7 + $index % 5)
                            : $term->starts_on->addWeeks($weeks)->addDays($index % 5);

                        $rows[] = [
                            'school_id' => $school->id,
                            'class_id' => $assignment->class_id,
                            'subject_id' => $assignment->subject_id,
                            'term_id' => $term->id,
                            'name' => $name,
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
            }
            $this->insert('assessments', $rows);

            $byClass = Enrollment::query()->where('academic_year_id', $year->id)->orderBy('student_id')->get(['student_id', 'class_id'])->groupBy('class_id');
            $held = DB::table('assessments')
                ->where('school_id', $school->id)
                ->whereIn('term_id', $year->terms->modelKeys())
                ->where('held_on', '<', $today->toDateString())
                ->orderBy('id')
                ->get(['id', 'class_id', 'max_marks', 'held_on', 'created_by']);

            $rows = [];
            foreach ($held as $assessment) {
                $enteredAt = CarbonImmutable::parse($assessment->held_on.' 16:00', $school->timezone)->addDays(2)->utc()->min($now);

                foreach ($byClass->get($assessment->class_id, collect()) as $enrollment) {
                    $ability[$enrollment->student_id] ??= $this->random->getInt(45, 95);
                    $absent = $this->random->getInt(1, 100) <= 3;
                    $percent = max(0, min(100, $ability[$enrollment->student_id] + $this->random->getInt(-15, 15)));
                    $step = $assessment->max_marks <= 20 ? 0.5 : 1;

                    $rows[] = [
                        'school_id' => $school->id,
                        'assessment_id' => $assessment->id,
                        'student_id' => $enrollment->student_id,
                        'marks' => $absent ? null : number_format(round($percent / 100 * $assessment->max_marks / $step) * $step, 2, '.', ''),
                        'remarks' => $absent ? 'Absent' : null,
                        'entered_by' => $assessment->created_by,
                        'created_at' => $enteredAt,
                        'updated_at' => $enteredAt,
                    ];

                    if (count($rows) === self::CHUNK) {
                        DB::table('grades')->insert($rows);
                        $rows = [];
                    }
                }
            }
            $this->insert('grades', $rows);
        }
    }

    /**
     * A tuition invoice per student for every term already invoiced (two weeks before it
     * starts), numbered per school and calendar year like GenerateTermInvoices. Earlier terms
     * are almost all paid; the current one is mixed, as in DemoFeesSeeder.
     *
     * @param  list<AcademicYear>  $years
     */
    private function invoices(School $school, array $years, User $office): void
    {
        $today = $school->today();
        $numbers = [];
        $prefix = config('schooldesk.fees.invoice_prefix');

        foreach ($years as $year) {
            $fee = FeeStructure::query()->create([
                'academic_year_id' => $year->id,
                'class_id' => null,
                'name' => 'Tuition',
                'amount' => self::TUITION,
                'frequency' => FeeFrequency::Term,
                'due_day_offset' => self::DUE_DAYS,
            ]);
            $enrollments = Enrollment::query()->where('academic_year_id', $year->id)->orderBy('class_id')->orderBy('roll_no')->get(['student_id', 'class_id']);

            foreach ($year->terms->sortBy('starts_on') as $term) {
                $issuedOn = $term->starts_on->subDays(14);
                if ($issuedOn->gt($today)) {
                    continue;
                }

                $issuedAt = CarbonImmutable::parse($issuedOn->toDateString().' 08:00', $school->timezone)->utc();
                $dueOn = $term->starts_on->addDays(self::DUE_DAYS);
                $settled = $dueOn->lt($today->subDays(60));
                $rows = [];

                foreach ($enrollments as $i => $enrollment) {
                    $number = $numbers[$issuedOn->year] = ($numbers[$issuedOn->year] ?? 0) + 1;
                    $state = match (true) {
                        $i % 100 === 99 => 'void',
                        $settled => $i % 50 === 7 ? 'partial' : 'paid',
                        $i % 10 <= 5 => 'paid',
                        $i % 10 <= 7 => 'partial',
                        default => 'open',
                    };

                    $rows[] = [
                        'school_id' => $school->id,
                        'student_id' => $enrollment->student_id,
                        'term_id' => $term->id,
                        'class_id' => $enrollment->class_id,
                        'number' => sprintf('%s-%d-%06d', $prefix, $issuedOn->year, $number),
                        'total' => self::TUITION,
                        'status' => match ($state) {
                            'void' => InvoiceStatus::Void->value,
                            'paid' => InvoiceStatus::Paid->value,
                            'partial' => InvoiceStatus::PartiallyPaid->value,
                            default => InvoiceStatus::Issued->value,
                        },
                        'due_on' => $dueOn->toDateString(),
                        'issued_at' => $issuedAt,
                        'voided_at' => $state === 'void' ? $issuedAt->addDays(3) : null,
                        // Counted as reminded, so the scheduler mails nobody about seeded invoices.
                        'reminders_sent' => 2,
                        'created_at' => $issuedAt,
                        'updated_at' => $issuedAt,
                    ];
                }
                $this->insert('invoices', $rows);

                $invoices = DB::table('invoices')->where('term_id', $term->id)->orderBy('id')->get(['id', 'status']);
                $this->insert('invoice_items', $invoices->map(fn (object $invoice): array => [
                    'invoice_id' => $invoice->id,
                    'fee_structure_id' => $fee->id,
                    'description' => $fee->name,
                    'amount' => self::TUITION,
                    'created_at' => $issuedAt,
                    'updated_at' => $issuedAt,
                ])->all());

                $payments = [];
                foreach ($invoices as $i => $invoice) {
                    $amount = match ($invoice->status) {
                        InvoiceStatus::Paid->value => self::TUITION,
                        InvoiceStatus::PartiallyPaid->value => intdiv(self::TUITION, 2),
                        default => 0,
                    };
                    if ($amount === 0) {
                        continue;
                    }

                    $method = [PaymentMethod::BankTransfer, PaymentMethod::Cash, PaymentMethod::Stripe][$i % 3];
                    $paidAt = CarbonImmutable::parse($dueOn->addDays($i % 21 - 10)->min($today->subDay())->toDateString().' 10:30', $school->timezone)->utc();
                    $payments[] = [
                        'school_id' => $school->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'refunded_amount' => 0,
                        'method' => $method->value,
                        'stripe_payment_intent_id' => $method === PaymentMethod::Stripe ? "pi_perf_{$school->id}_{$invoice->id}" : null,
                        'paid_at' => $paidAt,
                        'recorded_by' => $method === PaymentMethod::Stripe ? null : $office->id,
                        'created_at' => $paidAt,
                        'updated_at' => $paidAt,
                    ];
                }
                $this->insert('payments', $payments);
            }
        }

        // Invoices generated later continue the numbering.
        foreach ($numbers as $calendarYear => $last) {
            DB::table('invoice_number_sequences')->insert([
                'school_id' => $school->id,
                'year' => $calendarYear,
                'last_number' => $last,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function status(int $absenceRate): AttendanceStatus
    {
        $roll = $this->random->getInt(1, 100);

        return match (true) {
            $roll <= $absenceRate => AttendanceStatus::Absent,
            $roll <= $absenceRate + 3 => AttendanceStatus::Late,
            $roll <= $absenceRate + 5 => AttendanceStatus::Excused,
            default => AttendanceStatus::Present,
        };
    }

    /**
     * @param  array<int, int>  $weights  value => weight
     */
    private function weighted(array $weights): int
    {
        $roll = $this->random->getInt(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights) ?? 1;
    }

    private function weekday(CarbonImmutable $date): CarbonImmutable
    {
        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        return $date;
    }
}
