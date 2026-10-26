<?php

declare(strict_types=1);

use App\Actions\Gdpr\AnonymiseStudent;
use App\Enums\ActivityArea;
use App\Enums\AttendanceStatus;
use App\Enums\Locale;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\AiCommentDraft;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\ImportRow;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentExport;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 09:00:00');
    foreach (setUpStudentRecords() as $key => $value) {
        $this->{$key} = $value;
    }
    $this->export = inSchool($this->school, fn () => StudentExport::factory()->for($this->school)->completed()->create(['student_id' => $this->ada->id]));
    $this->anonymise = fn () => actingInSchool($this->admin, $this->school)
        ->post(route('students.anonymise', $this->ada), ['confirmation' => 'S-1']);
    $this->files = [$this->ada->photo_path, $this->card->pdfPath(), $this->payment->receiptPath(), $this->export->filePath()];
});

it('replaces the student\'s personal data and deletes their files', function (): void {
    ($this->anonymise)()->assertRedirect(route('students.show', $this->ada))
        ->assertSessionHas('status', 'The student\'s personal data was erased.');

    $ada = inSchool($this->school, fn () => Student::query()->findOrFail($this->ada->id));
    expect($ada->only(['admission_no', 'first_name', 'last_name', 'gender', 'address', 'photo_path']))->toBe([
        'admission_no' => "ANON-{$ada->id}",
        'first_name' => AnonymiseStudent::FIRST_NAME,
        'last_name' => AnonymiseStudent::LAST_NAME,
        'gender' => null,
        'address' => null,
        'photo_path' => null,
    ])
        ->and($ada->date_of_birth->toDateString())->toBe(AnonymiseStudent::DATE_OF_BIRTH)
        ->and($ada->anonymised_at?->toDateTimeString())->toBe('2026-10-20 09:00:00')
        ->and($ada->fullName())->toBe('Anonymised student');

    foreach ($this->files as $file) {
        expect(Storage::disk('local')->exists($file))->toBeFalse("{$file} still exists");
    }
});

it('keeps marks, attendance, results and finances without anything that identifies the student', function (): void {
    ($this->anonymise)();

    inSchool($this->school, function (): void {
        $grade = Grade::query()->where('student_id', $this->ada->id)->sole();
        expect($grade->marks)->toBe('18.00')->and($grade->remarks)->toBeNull();

        $absence = AttendanceRecord::query()->where('student_id', $this->ada->id)->sole();
        expect($absence->status)->toBe(AttendanceStatus::Absent)->and($absence->note)->toBeNull();

        $card = ReportCard::query()->findOrFail($this->card->id);
        // toEqual: MySQL's JSON columns do not keep the key order.
        expect($card->data['student'])->toEqual(['id' => $this->ada->id, 'first_name' => 'Anonymised', 'last_name' => 'Student', 'admission_no' => "ANON-{$this->ada->id}"])
            ->and($card->data['subjects'])->not->toBeEmpty()
            ->and($card->teacher_comment)->toBeNull()
            ->and($card->pdf_path)->toBeNull()
            ->and(AiCommentDraft::query()->count())->toBe(0);

        expect(Invoice::query()->findOrFail($this->invoice->id)->total)->toBe(120_000)
            ->and(Payment::query()->findOrFail($this->payment->id)->receipt_path)->toBeNull()
            ->and(StudentExport::query()->count())->toBe(0);
    });
});

it('unlinks guardians, deleting those with no other children here', function (): void {
    ($this->anonymise)();

    inSchool($this->school, function (): void {
        expect(Guardian::query()->whereKey($this->greta->id)->exists())->toBeFalse()
            ->and(Student::query()->findOrFail($this->ada->id)->guardians()->count())->toBe(0)
            ->and($this->ben->guardians()->pluck('name')->all())->toBe(['Paula Parent']);
    });
});

it('deletes notifications and import rows about the student', function (): void {
    ($this->anonymise)();

    expect($this->parent->notifications()->get()->pluck('data.first_name')->all())->toBe(['Ben'])
        ->and(ImportRow::query()->count())->toBe(0);
});

it('removes personal data from the audit log and logs the erasure', function (): void {
    ($this->anonymise)();

    $entries = inSchool($this->school, fn () => Activity::query()->where('student_id', $this->ada->id)->orderBy('id')->get());
    $logged = $entries->flatMap(fn (Activity $a): array => [
        ...array_keys($a->attribute_changes?->get('attributes', []) ?? []),
        ...array_keys($a->attribute_changes?->get('old', []) ?? []),
    ])->unique()->values()->all();

    expect($logged)->not->toContain('first_name', 'last_name', 'admission_no', 'date_of_birth', 'gender', 'address', 'remarks')
        ->and($logged)->toContain('status', 'marks', 'amount');

    $erasure = $entries->last();
    expect($erasure->log_name)->toBe(ActivityArea::Gdpr->value)
        ->and($erasure->description)->toBe('anonymised')
        ->and($erasure->causer_id)->toBe($this->admin->id);

    $this->admin->update(['locale' => Locale::German->value]);
    actingInSchool($this->admin, $this->school)->get(route('audit-log.index', ['area' => 'gdpr']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activities.data.0.summary', 'Personenbezogene Daten gelöscht')
            ->where('activities.data.0.student.name', 'Anonymisierter Schüler'));
});

it('shows the student as anonymised, and allows no further changes', function (): void {
    ($this->anonymise)();

    actingInSchool($this->admin, $this->school)->get(route('students.show', $this->ada))
        ->assertInertia(fn (Assert $page) => $page
            ->where('student.name', 'Anonymised student')
            ->where('student.anonymised_on', '20 Oct 2026')
            ->where('student.date_of_birth', null)
            ->where('student.can', ['update' => false, 'delete' => true, 'export' => false, 'anonymise' => false]));

    actingInSchool($this->admin, $this->school)->get(route('students.edit', $this->ada))->assertForbidden();
    actingInSchool($this->admin, $this->school)->post(route('students.export', $this->ada))->assertForbidden();
    ($this->anonymise)()->assertForbidden();
});

it('needs the admission number typed to confirm', function (): void {
    actingInSchool($this->admin, $this->school)
        ->post(route('students.anonymise', $this->ada), ['confirmation' => 's-1'])
        ->assertSessionHasErrors(['confirmation' => 'Type the admission number exactly as shown.']);

    expect(inSchool($this->school, fn () => Student::query()->findOrFail($this->ada->id)->anonymised_at))->toBeNull()
        ->and(Storage::disk('local')->exists($this->files[0]))->toBeTrue();
});

it('is for data protection admins only', function (Role $role): void {
    actingInSchool(memberOf($this->school, $role), $this->school)
        ->post(route('students.anonymise', $this->ada), ['confirmation' => 'S-1'])
        ->assertForbidden();
})->with([Role::Teacher, Role::Parent]);
