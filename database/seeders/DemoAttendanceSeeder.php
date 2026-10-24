<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Up to three months of attendance for a demo school, every weekday up to yesterday: today
 * is left open, so the demo teacher can take it. Recorded by each class's class teacher.
 *
 * Most students are rarely absent, a few often, so the monthly reports have something to
 * show. Absences are marked as already notified: seeding never sends mail. Deterministic
 * per school and day, and bulk-inserted like DemoRosterSeeder.
 */
class DemoAttendanceSeeder extends Seeder
{
    private const MONTHS = 3;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function seedSchool(School $school): void
    {
        $random = new Randomizer(new Mt19937(crc32($school->slug.'-attendance')));

        $this->currentSchool->run($school, function (School $school) use ($random): void {
            $year = AcademicYear::query()->current()->firstOrFail();
            $to = $school->today()->subDay()->min($year->ends_on);
            $from = $to->subMonths(self::MONTHS)->max($year->starts_on);

            $teachers = SchoolClass::query()->where('academic_year_id', $year->id)->pluck('class_teacher_id', 'id');
            $enrollments = Enrollment::query()->where('academic_year_id', $year->id)->orderBy('id')->get(['student_id', 'class_id', 'enrolled_on']);

            // How often each student is absent: most rarely, some now and then, a few often.
            $absenceRate = [];
            foreach ($enrollments as $enrollment) {
                $roll = $random->getInt(1, 100);
                $absenceRate[$enrollment->student_id] = $roll <= 85 ? 2 : ($roll <= 97 ? 7 : 18);
            }

            $rows = [];
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }

                $recordedAt = CarbonImmutable::parse($day->toDateString().' 08:15', $school->timezone)->utc();

                foreach ($enrollments as $enrollment) {
                    if ($enrollment->enrolled_on->gt($day)) {
                        continue;
                    }

                    $status = $this->status($random, $absenceRate[$enrollment->student_id]);
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

                    if (count($rows) === 1000) {
                        DB::table('attendance_records')->insert($rows);
                        $rows = [];
                    }
                }
            }

            if ($rows !== []) {
                DB::table('attendance_records')->insert($rows);
            }
        });
    }

    private function status(Randomizer $random, int $absenceRate): AttendanceStatus
    {
        $roll = $random->getInt(1, 100);

        return match (true) {
            $roll <= $absenceRate => AttendanceStatus::Absent,
            $roll <= $absenceRate + 3 => AttendanceStatus::Late,
            $roll <= $absenceRate + 5 => AttendanceStatus::Excused,
            default => AttendanceStatus::Present,
        };
    }
}
