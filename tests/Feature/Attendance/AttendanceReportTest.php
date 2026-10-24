<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->travelTo('2026-10-14 10:00:00');

    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->teacher = memberOf($this->school, Role::Teacher);
    $this->class->update(['class_teacher_id' => $this->teacher->id]);

    $enrol = function (string $first, int $roll): Student {
        $student = Student::factory()->for($this->school)->create(['first_name' => $first, 'last_name' => 'Test', 'admission_no' => "S-{$roll}"]);
        Enrollment::factory()->for($this->class, 'schoolClass')->for($student)->create(['roll_no' => $roll]);

        return $student;
    };
    $this->ada = $enrol('Ada', 1);
    $this->ben = $enrol('Ben', 2);
    $this->cleo = $enrol('Cleo', 3); // nothing recorded for her

    $record = fn (Student $student, string $date, AttendanceStatus $status, ?SchoolClass $class = null) => AttendanceRecord::factory()
        ->for($class ?? $this->class, 'schoolClass')
        ->for($student)
        ->create(['date' => $date, 'status' => $status]);

    $record($this->ada, '2026-10-01', AttendanceStatus::Present);
    $record($this->ben, '2026-10-01', AttendanceStatus::Absent);
    $record($this->ada, '2026-10-02', AttendanceStatus::Late);
    $record($this->ben, '2026-10-02', AttendanceStatus::Excused);
    $record($this->ada, '2026-09-30', AttendanceStatus::Absent); // another month

    // Taken in another class: not part of this class's report.
    $other = SchoolClass::factory()->for($this->school)->for($this->year)->create();
    $record(Student::factory()->for($this->school)->create(), '2026-10-01', AttendanceStatus::Absent, $other);

    $this->as = fn () => actingInSchool($this->teacher, $this->school);
});

it('reports a class\'s month as a grid with totals and rates', function (): void {
    ($this->as)()->get(route('attendance.report', [$this->class, 'month' => '2026-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Report')
            ->where('month', '2026-10')
            ->where('months', [
                ['value' => '2026-10', 'label' => 'October 2026'],
                ['value' => '2026-09', 'label' => 'September 2026'],
                ['value' => '2026-08', 'label' => 'August 2026'],
            ])
            ->where('report.days', ['2026-10-01', '2026-10-02'])
            ->has('report.students', 3)
            ->where('report.students.0.name', 'Ada Test')
            ->where('report.students.0.statuses', ['2026-10-01' => 'P', '2026-10-02' => 'L'])
            ->where('report.students.0.rate', 100) // late counts as attended
            ->where('report.students.1.totals', ['present' => 0, 'absent' => 1, 'late' => 0, 'excused' => 1])
            ->where('report.students.1.rate', 0)
            ->where('report.students.2.name', 'Cleo Test')
            ->where('report.students.2.rate', null)
            ->where('report.daily', [
                ['date' => '2026-10-01', 'present' => 1, 'absent' => 1, 'late' => 0, 'excused' => 0],
                ['date' => '2026-10-02', 'present' => 0, 'absent' => 0, 'late' => 1, 'excused' => 1],
            ])
            ->where('report.totals', ['present' => 1, 'absent' => 1, 'late' => 1, 'excused' => 1, 'rate' => 50]));
});

it('shows the current month by default', function (): void {
    ($this->as)()->get(route('attendance.report', $this->class))
        ->assertInertia(fn (Assert $page) => $page->where('month', '2026-10')->has('report.days', 2));
});

it('only offers months of the academic year up to now', function (string $month): void {
    ($this->as)()->get(route('attendance.report', [$this->class, 'month' => $month]))
        ->assertSessionHasErrors('month');
})->with(['2026-11', '2026-07', 'october']);

it('exports the month as a spreadsheet', function (): void {
    $response = ($this->as)()->get(route('attendance.export', [$this->class, 'month' => '2026-10']))
        ->assertOk()
        ->assertDownload('attendance-grade-7-a-2026-10.xlsx');

    $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
    $rows = $sheet->toArray(null, false, false); // raw values, not formatted strings

    expect($sheet->getTitle())->toBe('October 2026')
        ->and($rows)->toHaveCount(5) // heading, 3 students, class total
        ->and($rows[0])->toBe(['Roll', 'Name', 'Admission no.', 'Thu 1', 'Fri 2', 'Present', 'Absent', 'Late', 'Excused', 'Attendance %'])
        ->and($rows[1])->toBe([1, 'Ada Test', 'S-1', 'P', 'L', 1, 0, 1, 0, 100.0])
        ->and($rows[3])->toBe([3, 'Cleo Test', 'S-3', null, null, 0, 0, 0, 0, null])
        ->and($rows[4][1])->toBe('Class Grade 7 A')
        ->and($rows[4][9])->toBe(50.0);
});

it('is only for teachers of the class and school admins', function (): void {
    $other = memberOf($this->school, Role::Teacher);

    actingInSchool($other, $this->school)->get(route('attendance.report', $this->class))->assertForbidden();
    actingInSchool($other, $this->school)->get(route('attendance.export', $this->class))->assertForbidden();

    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->get(route('attendance.report', $this->class))
        ->assertOk();
});
