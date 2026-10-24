<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Jobs\SendAbsenceNotification;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentAbsent;
use App\Support\CurrentSchool;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Notification::fake();
    $this->travelTo('2026-10-14 10:00:00');

    [$this->school, , $this->class] = setUpSchool();
    $this->teacher = memberOf($this->school, Role::Teacher);
    $this->class->update(['class_teacher_id' => $this->teacher->id]);

    $this->student = Student::factory()->for($this->school)->create(['first_name' => 'Mila', 'last_name' => 'Okafor']);
    Enrollment::factory()->for($this->class, 'schoolClass')->for($this->student)->create();

    // The primary guardian has a parent portal account; the second guardian has none.
    $this->parent = User::factory()->create(['email' => 'ada@example.com']);
    $this->student->guardians()->attach(
        Guardian::factory()->for($this->school)->create(['user_id' => $this->parent->id, 'email' => 'ada@example.com']),
        ['is_primary' => true],
    );
    $this->student->guardians()->attach(Guardian::factory()->for($this->school)->create(), ['is_primary' => false]);

    $this->mark = fn (string $status) => actingInSchool($this->teacher, $this->school)
        ->put(route('attendance.update', [$this->class, '2026-10-14']), [
            'records' => [['student_id' => $this->student->id, 'status' => $status, 'note' => null]],
        ])
        ->assertSessionHasNoErrors();
    $this->record = fn (): AttendanceRecord => inSchool($this->school, fn () => AttendanceRecord::query()->sole());
});

it('queues a delayed notification when a student is marked absent', function (): void {
    Queue::fake();
    config(['schooldesk.attendance.absence_notification_delay_minutes' => 10]);

    ($this->mark)('absent');
    ($this->mark)('absent'); // saved again: still the same absence

    Queue::assertPushed(SendAbsenceNotification::class, 1);
    Queue::assertPushed(SendAbsenceNotification::class, fn (SendAbsenceNotification $job): bool => $job->schoolId === $this->school->id
        && $job->attendanceRecordId === ($this->record)()->id
        && $job->delay instanceof DateTimeInterface
        && $job->delay->getTimestamp() === now()->addMinutes(10)->getTimestamp());
});

it('queues nothing for present, late or excused students', function (string $status): void {
    Queue::fake();

    ($this->mark)($status);

    Queue::assertNotPushed(SendAbsenceNotification::class);
})->with(['present', 'late', 'excused']);

it('tells the primary guardian by mail and in the parent portal', function (): void {
    ($this->mark)('absent'); // the test queue runs the job straight away

    Notification::assertSentTo($this->parent, StudentAbsent::class, fn (StudentAbsent $n, array $channels): bool => $channels === ['mail', 'database']
        && $n->record->student_id === $this->student->id);
    Notification::assertCount(1);
    expect(($this->record)()->guardian_notified_at)->not->toBeNull();
});

it('mails a primary guardian without a portal account', function (): void {
    inSchool($this->school, fn () => Guardian::query()->where('user_id', $this->parent->id)->update(['user_id' => null]));

    ($this->mark)('absent');

    Notification::assertSentOnDemand(StudentAbsent::class, fn (StudentAbsent $n, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
        && array_keys($notifiable->routes['mail']) === ['ada@example.com']);
    Notification::assertNotSentTo($this->parent, StudentAbsent::class);
});

it('notifies at most once per student and day', function (): void {
    ($this->mark)('absent');
    ($this->mark)('present');
    ($this->mark)('absent');

    // A retried or duplicate job does not send again either.
    (new SendAbsenceNotification($this->school->id, ($this->record)()->id))->handle(app(CurrentSchool::class));

    Notification::assertSentToTimes($this->parent, StudentAbsent::class, 1);
});

it('sends nothing if the absence was corrected before the job ran', function (): void {
    Queue::fake();
    ($this->mark)('absent');
    ($this->mark)('present');

    (new SendAbsenceNotification($this->school->id, ($this->record)()->id))->handle(app(CurrentSchool::class));

    Notification::assertNothingSent();
    expect(($this->record)()->guardian_notified_at)->toBeNull();
});

it('sends nothing to students without a primary guardian', function (): void {
    inSchool($this->school, fn () => $this->student->guardians()->updateExistingPivot(
        $this->student->guardians()->wherePivot('is_primary', true)->sole()->id,
        ['is_primary' => false],
    ));

    ($this->mark)('absent');

    Notification::assertNothingSent();
    expect(($this->record)()->guardian_notified_at)->toBeNull();
});

it('names the child by first name only', function (): void {
    ($this->mark)('absent');
    $record = inSchool($this->school, fn () => AttendanceRecord::query()->with(['school', 'student', 'schoolClass'])->sole());
    $notification = new StudentAbsent($record);

    $mail = $notification->toMail($this->parent);
    $text = $mail->subject.' '.implode(' ', $mail->introLines);

    expect($mail->subject)->toBe('Mila was absent from school')
        ->and($text)->toContain('Grade 7 A')->toContain('Wednesday, 14 October 2026')
        ->and($text)->not->toContain('Okafor')
        ->and($notification->toArray($this->parent))->toBe([
            'student_id' => $this->student->id,
            'first_name' => 'Mila',
            'class' => 'Grade 7 A',
            'date' => '2026-10-14',
        ]);
});
