<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Queue::fake();
    $this->travelTo('2026-10-14 10:00:00'); // a Wednesday

    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->teacher = memberOf($this->school, Role::Teacher);
    $this->class->update(['class_teacher_id' => $this->teacher->id]);

    /** @var Collection<int, Student> $students */
    $students = collect(['Ada', 'Ben', 'Cleo'])->map(function (string $name, int $i): Student {
        $student = Student::factory()->for($this->school)->create(['first_name' => $name, 'last_name' => 'Test']);
        Enrollment::factory()->for($this->class, 'schoolClass')->for($student)->create(['roll_no' => $i + 1]);

        return $student;
    });
    $this->students = $students;

    $this->grid = fn (array $statuses = []): array => ['records' => $this->students->map(fn (Student $s, int $i): array => [
        'student_id' => $s->id,
        'status' => $statuses[$i] ?? 'present',
        'note' => null,
    ])->all()];
    $this->save = fn (array $statuses = [], string $date = '2026-10-14') => actingInSchool($this->teacher, $this->school)
        ->put(route('attendance.update', [$this->class, $date]), ($this->grid)($statuses));
    $this->records = fn (): Collection => inSchool($this->school, fn () => AttendanceRecord::query()->orderBy('student_id')->get());
});

it('shows the class grid, with nothing recorded yet', function (): void {
    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.show', [$this->class, '2026-10-14']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Show')
            ->where('schoolClass.name', 'Grade 7 A')
            ->where('date', '2026-10-14')
            ->where('previousDate', '2026-10-13')
            ->where('nextDate', null) // today: no attendance in advance
            ->has('students', 3)
            ->where('students.0.name', 'Ada Test')
            ->where('students.0.status', null)
            ->where('recorded.count', 0)
            ->has('statuses', 4));
});

it('steps over weekends to the previous school day', function (): void {
    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.show', [$this->class, '2026-10-12'])) // a Monday
        ->assertInertia(fn (Assert $page) => $page
            ->where('previousDate', '2026-10-09')
            ->where('nextDate', '2026-10-13'));
});

it('saves the whole class at once', function (): void {
    ($this->save)(['present', 'absent', 'late'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Attendance saved for Grade 7 A: 1 absent.');

    $records = ($this->records)();
    expect($records)->toHaveCount(3)
        ->and($records->map(fn (AttendanceRecord $r): AttendanceStatus => $r->status)->all())
        ->toBe([AttendanceStatus::Present, AttendanceStatus::Absent, AttendanceStatus::Late])
        ->and($records->pluck('class_id')->unique()->all())->toBe([$this->class->id])
        ->and($records->pluck('recorded_by')->unique()->all())->toBe([$this->teacher->id])
        ->and($records->first()?->date->toDateString())->toBe('2026-10-14');
});

it('updates the day\'s records when saved again', function (): void {
    ($this->save)(['absent']);
    ($this->save)(['excused'])->assertSessionHas('status', 'Attendance saved for Grade 7 A: everyone present.');

    $records = ($this->records)();
    expect($records)->toHaveCount(3)
        ->and($records->first()?->status)->toBe(AttendanceStatus::Excused);

    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.show', [$this->class, '2026-10-14']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('students.0.status', 'excused')
            ->where('students.1.status', 'present')
            ->where('recorded.count', 3)
            ->where('recorded.by', $this->teacher->name));
});

it('keeps notes and drops empty ones', function (): void {
    $input = ($this->grid)(['late']);
    $input['records'][0]['note'] = '  Bus was late  ';
    $input['records'][1]['note'] = '   ';

    actingInSchool($this->teacher, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), $input)
        ->assertSessionHasNoErrors();

    expect(($this->records)()->pluck('note')->all())->toBe(['Bus was late', null, null]);
});

it('only lists students enrolled in the class on the day', function (): void {
    $left = Student::factory()->for($this->school)->create();
    Enrollment::factory()->for($this->class, 'schoolClass')->for($left)->create(['left_on' => '2026-10-10']);
    $later = Student::factory()->for($this->school)->create();
    Enrollment::factory()->for($this->class, 'schoolClass')->for($later)->create(['enrolled_on' => '2026-10-20']);

    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.show', [$this->class, '2026-10-14']))
        ->assertInertia(fn (Assert $page) => $page->has('students', 3));

    $input = ($this->grid)();
    $input['records'][] = ['student_id' => $left->id, 'status' => 'present', 'note' => null];

    actingInSchool($this->teacher, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), $input)
        ->assertSessionHasErrors('records');

    expect(($this->records)())->toHaveCount(0);
});

it('rejects students of another school', function (): void {
    $input = ($this->grid)();
    $input['records'][0]['student_id'] = Student::factory()->create()->id;

    actingInSchool($this->teacher, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), $input)
        ->assertSessionHasErrors('records');
});

it('validates the records', function (Closure $change, string $error): void {
    $input = ($this->grid)();
    $change($input);

    actingInSchool($this->teacher, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), $input)
        ->assertSessionHasErrors($error);
})->with([
    'unknown status' => [function (array &$input): void {
        $input['records'][0]['status'] = 'asleep';
    }, 'records.0.status'],
    'student twice' => [function (array &$input): void {
        $input['records'][1]['student_id'] = $input['records'][0]['student_id'];
    }, 'records.1.student_id'],
    'long note' => [function (array &$input): void {
        $input['records'][0]['note'] = str_repeat('x', 256);
    }, 'records.0.note'],
    'no records' => [function (array &$input): void {
        $input['records'] = [];
    }, 'records'],
]);

it('takes no attendance in advance or outside the academic year', function (string $date, string $message): void {
    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.show', [$this->class, $date]))
        ->assertRedirect(route('attendance.index'))
        ->assertSessionHasErrors(['date' => $message]);

    ($this->save)([], $date)->assertSessionHasErrors(['date' => $message]);
    expect(($this->records)())->toHaveCount(0);
})->with([
    'tomorrow' => ['2026-10-15', 'Attendance cannot be taken for a future date.'],
    'before the year' => ['2026-07-20', '20 July 2026 is outside the academic year 2026/27.'],
]);

it('uses today in the school\'s timezone', function (): void {
    $this->travelTo('2026-10-14 23:30:00'); // already 01:30 on the 15th in Berlin

    ($this->save)([], '2026-10-15')->assertSessionHasNoErrors();
});

it('returns 404 for impossible dates', function (): void {
    actingInSchool($this->teacher, $this->school)
        ->get('/attendance/'.$this->class->id.'/2026-02-31')
        ->assertNotFound();
});

it('lists a teacher\'s own classes with today\'s progress', function (): void {
    $taught = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8', 'section' => 'A']);
    TeachingAssignment::query()->create([
        'school_id' => $this->school->id,
        'class_id' => $taught->id,
        'subject_id' => Subject::factory()->for($this->school)->create()->id,
        'teacher_id' => $this->teacher->id,
    ]);
    SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 9', 'section' => 'A']);
    ($this->save)(['absent']);

    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->where('date', '2026-10-14')
            ->has('classes', 2)
            ->where('classes.0.name', 'Grade 7 A')
            ->where('classes.0.students_count', 3)
            ->where('classes.0.recorded_count', 3)
            ->where('classes.0.is_class_teacher', true)
            ->where('classes.1.name', 'Grade 8 A')
            ->where('classes.1.is_class_teacher', false));

    actingInSchool($this->teacher, $this->school)
        ->get(route('attendance.index', ['date' => '2026-10-13']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', '2026-10-13')
            ->where('classes.0.recorded_count', 0));
});

it('lets school admins take attendance for every class', function (): void {
    $admin = memberOf($this->school, Role::SchoolAdmin);
    SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 9', 'section' => 'A']);

    actingInSchool($admin, $this->school)
        ->get(route('attendance.index'))
        ->assertInertia(fn (Assert $page) => $page->has('classes', 2));

    actingInSchool($admin, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), ($this->grid)())
        ->assertSessionHasNoErrors();
});

it('forbids teachers to take attendance for classes they do not teach', function (): void {
    $other = memberOf($this->school, Role::Teacher);

    actingInSchool($other, $this->school)->get(route('attendance.show', [$this->class, '2026-10-14']))->assertForbidden();
    actingInSchool($other, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), ($this->grid)())
        ->assertForbidden();
    actingInSchool($other, $this->school)
        ->get(route('attendance.index'))
        ->assertInertia(fn (Assert $page) => $page->has('classes', 0));

    expect(($this->records)())->toHaveCount(0);
});

it('is not for parents', function (): void {
    $parent = memberOf($this->school, Role::Parent);

    actingInSchool($parent, $this->school)->get(route('attendance.index'))->assertForbidden();
    actingInSchool($parent, $this->school)->get(route('attendance.show', [$this->class, '2026-10-14']))->assertForbidden();
});

it('keeps a class with attendance history from being deleted', function (): void {
    ($this->save)();
    inSchool($this->school, fn () => Enrollment::query()->delete());

    actingInSchool(memberOf($this->school, Role::SchoolAdmin), $this->school)
        ->delete(route('classes.destroy', $this->class))
        ->assertSessionHasErrors('class');

    expect(inSchool($this->school, fn () => SchoolClass::query()->whereKey($this->class->id)->exists()))->toBeTrue();
});
