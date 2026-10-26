<?php

declare(strict_types=1);

use App\Actions\Fees\RecordPayment;
use App\Enums\ActivityArea;
use App\Enums\Locale;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Invoice;
use App\Models\School;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 09:00:00');
    [$this->school] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin, ['name' => 'Alice Admin']);

    // Another school's history, created before the first request (see Pest.php: inSchool).
    $this->otherSchool = School::factory()->completedSetup()->create();
    $this->otherStudent = Student::factory()->for($this->otherSchool)->create(['first_name' => 'Otto']);

    $this->ada = Student::factory()->for($this->school)->create([
        'first_name' => 'Ada', 'last_name' => 'Lovelace', 'admission_no' => 'S-1', 'date_of_birth' => '2014-03-02', 'address' => null,
    ]);
    $this->ben = Student::factory()->for($this->school)->create(['first_name' => 'Ben', 'last_name' => 'Test']);

    $this->travelTo('2026-10-21 09:00:00');
    $this->actingAs($this->admin);
    inSchool($this->school, fn () => $this->ada->update(['status' => StudentStatus::Left, 'address' => 'Hauptstr. 1']));
    auth()->logout();
});

it('shows the school\'s log, newest first, in the reader\'s language', function (): void {
    actingInSchool($this->admin, $this->school)->get(route('audit-log.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AuditLog/Index')
            ->has('activities.data', 3)
            ->where('activities.data.0.summary', 'Student details changed')
            ->where('activities.data.0.area', 'Students')
            ->where('activities.data.0.causer', 'Alice Admin')
            ->where('activities.data.0.student', ['id' => $this->ada->id, 'name' => 'Ada Lovelace', 'admission_no' => 'S-1'])
            ->where('activities.data.0.record', ['label' => 'Ada Lovelace', 'url' => route('students.show', $this->ada)])
            ->where('activities.data.0.changes', [
                ['field' => 'Address', 'old' => null, 'new' => 'Hauptstr. 1'],
                ['field' => 'Status', 'old' => 'Active', 'new' => 'Left'],
            ])
            ->where('activities.data.2.summary', 'Student added')
            ->where('activities.data.2.causer', null)
            ->where('activities.data.2.changes', fn ($changes): bool => collect($changes)->contains(['field' => 'Date of birth', 'old' => null, 'new' => '2 Mar 2014']))
            ->where('users', [['id' => $this->admin->id, 'name' => 'Alice Admin']])
            ->has('areas', count(ActivityArea::cases())));

    $this->admin->update(['locale' => Locale::German->value]);
    actingInSchool($this->admin, $this->school)->get(route('audit-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activities.data.0.summary', 'Schülerdaten geändert')
            ->where('activities.data.0.changes.1', ['field' => 'Status', 'old' => 'Aktiv', 'new' => 'Abgegangen']));
});

it('describes invoices and payments with amounts in the school\'s currency', function (): void {
    Queue::fake();
    Storage::fake('local');
    $this->travelTo('2026-08-05 10:00:00');
    $setup = setUpFees();
    generateInvoices($setup['school'], $setup['term']);
    $invoice = inSchool($setup['school'], fn () => Invoice::query()->where('student_id', $setup['students'][0]->id)->sole());

    $this->actingAs($setup['admin']);
    inSchool($setup['school'], fn () => app(RecordPayment::class)->handle($invoice, new Money(50_000, 'EUR'), PaymentMethod::Cash, $setup['admin'], now()));

    $get = fn (string $area) => actingInSchool($setup['admin'], $setup['school'])->get(route('audit-log.index', ['area' => $area]));

    $get('payments')->assertInertia(fn (Assert $page) => $page
        ->where('activities.data.0.summary', 'Payment recorded')
        ->where('activities.data.0.student.name', 'Ada Test')
        ->where('activities.data.0.record', ['label' => 'SD-2026-000001', 'url' => route('invoices.show', $invoice->id)])
        ->where('activities.data.0.changes', [
            ['field' => 'Amount', 'old' => null, 'new' => '€500.00'],
            ['field' => 'Refunded', 'old' => null, 'new' => '€0.00'],
            ['field' => 'Method', 'old' => null, 'new' => 'Cash'],
            ['field' => 'Paid on', 'old' => null, 'new' => '5 Aug 2026'],
        ]));

    $get('invoices')->assertInertia(fn (Assert $page) => $page
        ->has('activities.data', 4)
        ->where('activities.data.0.summary', 'Invoice updated')
        ->where('activities.data.0.changes', [['field' => 'Status', 'old' => 'Issued', 'new' => 'Partially paid']])
        ->where('activities.data.3.summary', 'Invoice issued')
        ->where('activities.data.3.causer', null)
        ->where('activities.data.3.changes.1', ['field' => 'Total', 'old' => null, 'new' => '€1,200.00']));
});

it('filters by area, author, date and student', function (): void {
    $get = fn (array $query) => actingInSchool($this->admin, $this->school)->get(route('audit-log.index', $query));

    $get(['area' => 'grades'])->assertInertia(fn (Assert $page) => $page->has('activities.data', 0));
    $get(['area' => 'students'])->assertInertia(fn (Assert $page) => $page->has('activities.data', 3));
    $get(['user_id' => $this->admin->id])->assertInertia(fn (Assert $page) => $page->has('activities.data', 1));
    $get(['from' => '2026-10-21'])->assertInertia(fn (Assert $page) => $page->has('activities.data', 1));
    $get(['to' => '2026-10-20'])->assertInertia(fn (Assert $page) => $page->has('activities.data', 2));
    $get(['student' => $this->ben->id])->assertInertia(fn (Assert $page) => $page
        ->has('activities.data', 1)
        ->where('student', ['id' => $this->ben->id, 'name' => 'Ben Test'])
        ->where('filters.student_id', $this->ben->id));

    $get(['area' => 'unknown'])->assertSessionHasErrors('area');
    $get(['from' => '2026-10-21', 'to' => '2026-10-20'])->assertSessionHasErrors('to');
});

it('never shows another school\'s entries, and 404s for its students', function (): void {
    actingInSchool($this->admin, $this->school)->get(route('audit-log.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activities.data', fn ($entries): bool => collect($entries)->every(
                fn (array $entry): bool => $entry['student']['id'] !== $this->otherStudent->id,
            )));

    actingInSchool($this->admin, $this->school)->get(route('audit-log.index', ['student' => $this->otherStudent->id]))
        ->assertNotFound();
});

it('is for school admins only', function (Role $role): void {
    actingInSchool(memberOf($this->school, $role), $this->school)->get(route('audit-log.index'))->assertForbidden();
})->with([Role::Teacher, Role::Parent]);
