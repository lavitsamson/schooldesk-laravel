<?php

declare(strict_types=1);

use App\Actions\Fees\GenerateTermInvoices;
use App\Actions\Fees\VoidInvoice;
use App\Actions\Guardians\NotifyPrimaryGuardian;
use App\Enums\FeeFrequency;
use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoiceIssuedNotification;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Notifications\InvoiceIssued;
use App\Support\CurrentSchool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo('2026-08-05 10:00:00');
    foreach (setUpFees() as $key => $value) {
        $this->{$key} = $value;
    }

    $this->invoices = fn () => inSchool($this->school, fn () => Invoice::query()->with(['items', 'student'])->orderBy('number')->get());
});

it('issues one invoice per enrolled student with the fees of their class', function (): void {
    FeeStructure::factory()->forClass($this->otherClass)->create(['name' => 'Lab fee', 'amount' => 5_000, 'due_day_offset' => 7]);

    expect(generateInvoices($this->school, $this->term))->toBe(3);

    $invoices = ($this->invoices)();
    expect($invoices->pluck('number')->all())->toBe(['SD-2026-000001', 'SD-2026-000002', 'SD-2026-000003'])
        ->and($invoices->pluck('student.first_name')->all())->toBe(['Ada', 'Ben', 'Cleo'])
        ->and($invoices->pluck('total')->all())->toBe([120_000, 120_000, 125_000])
        ->and($invoices->every(fn (Invoice $i): bool => $i->status === InvoiceStatus::Issued))->toBeTrue()
        ->and($invoices[0]->class_id)->toBe($this->class->id)
        ->and($invoices[0]->term_id)->toBe($this->term->id)
        // Due 14 days after the term starts; Cleo's lab fee is due after 7, so hers is too.
        ->and($invoices[0]->due_on->toDateString())->toBe('2026-08-31')
        ->and($invoices[2]->due_on->toDateString())->toBe('2026-08-24')
        ->and($invoices[2]->items->pluck('description')->all())->toBe(['Tuition', 'Lab fee'])
        ->and($invoices[2]->items->pluck('amount')->all())->toBe([120_000, 5_000]);
});

it('makes invoices generated after the term started due today at the earliest', function (): void {
    $this->travelTo('2026-09-10 10:00:00');

    generateInvoices($this->school, $this->term);

    expect(($this->invoices)()->first()->due_on->toDateString())->toBe('2026-09-10');
});

it('is idempotent: running again only adds students who have no invoice yet', function (): void {
    expect(generateInvoices($this->school, $this->term))->toBe(3)
        ->and(generateInvoices($this->school, $this->term))->toBe(0);

    $late = Student::factory()->for($this->school)->create(['first_name' => 'Dan']);
    Enrollment::factory()->for($this->class, 'schoolClass')->for($late)->create(['enrolled_on' => '2026-09-01']);

    expect(generateInvoices($this->school, $this->term))->toBe(1)
        ->and(($this->invoices)()->pluck('number')->last())->toBe('SD-2026-000004');
});

it('does not invoice a voided student and term again', function (): void {
    generateInvoices($this->school, $this->term);
    $ada = ($this->invoices)()->first();
    inSchool($this->school, fn () => app(VoidInvoice::class)->handle($ada));

    expect(generateInvoices($this->school, $this->term))->toBe(0);
});

it('charges annual fees once a year and term fees every term', function (): void {
    FeeStructure::factory()->for($this->year)->create(['name' => 'Activities', 'amount' => 15_000, 'frequency' => FeeFrequency::Annual]);

    generateInvoices($this->school, $this->term);
    generateInvoices($this->school, $this->nextTerm);

    $ada = ($this->invoices)()->where('student_id', $this->students[0]->id)->values();
    expect($ada)->toHaveCount(2)
        ->and($ada[0]->items->pluck('description')->all())->toBe(['Tuition', 'Activities'])
        ->and($ada[1]->items->pluck('description')->all())->toBe(['Tuition']);
});

it('charges one-off fees only to students in their first year at the school', function (): void {
    FeeStructure::factory()->for($this->year)->create(['name' => 'Admission fee', 'amount' => 25_000, 'frequency' => FeeFrequency::OneOff]);

    // Ben was here last year.
    $lastYear = AcademicYear::factory()->for($this->school)->create(['name' => '2025/26', 'starts_on' => '2025-08-01', 'ends_on' => '2026-07-31', 'is_current' => false]);
    $lastYearClass = SchoolClass::factory()->for($this->school)->for($lastYear)->create();
    Enrollment::factory()->for($lastYearClass, 'schoolClass')->for($this->students[1])->create(['enrolled_on' => '2025-08-01', 'left_on' => '2026-07-31']);

    generateInvoices($this->school, $this->term);
    generateInvoices($this->school, $this->nextTerm);

    $items = ($this->invoices)()->groupBy('student.first_name')->map(fn ($invoices) => $invoices->flatMap->items->pluck('description')->all());
    expect($items['Ada'])->toBe(['Tuition', 'Admission fee', 'Tuition'])
        ->and($items['Ben'])->toBe(['Tuition', 'Tuition']);
});

it('skips students who left, and terms without fees', function (): void {
    inSchool($this->school, fn () => Enrollment::query()->where('student_id', $this->students[2]->id)->update(['left_on' => '2026-08-01']));
    inSchool($this->school, fn () => $this->students[1]->update(['status' => 'left']));

    expect(generateInvoices($this->school, $this->term))->toBe(1);

    $this->tuition->delete();
    expect(generateInvoices($this->school, $this->nextTerm))->toBe(0);
});

it('numbers invoices per school and year without gaps', function (): void {
    $other = setUpFees();

    generateInvoices($this->school, $this->term);
    generateInvoices($other['school'], $other['term']);

    expect(inSchool($other['school'], fn () => Invoice::query()->orderBy('number')->pluck('number')->all()))
        ->toBe(['SD-2026-000001', 'SD-2026-000002', 'SD-2026-000003']);
});

it('refuses to run twice at the same time for a term', function (): void {
    $lock = Cache::lock("fees:generate:{$this->school->id}:{$this->term->id}", 60);
    $lock->get();

    expect(fn () => generateInvoices($this->school, $this->term))->toThrow(ValidationException::class);

    $lock->release();
});

it('queues an invoice notification per invoice, sent to the primary guardian', function (): void {
    Notification::fake();
    Queue::fake();

    expect(inSchool($this->school, fn () => app(GenerateTermInvoices::class)->handle($this->term)))->toBe(3);
    Queue::assertPushed(SendInvoiceIssuedNotification::class, 3);

    $ada = ($this->invoices)()->first();
    (new SendInvoiceIssuedNotification($this->school->id, $ada->id))->handle(app(CurrentSchool::class), app(NotifyPrimaryGuardian::class));

    Notification::assertSentTo($this->parent, InvoiceIssued::class, fn (InvoiceIssued $n, array $channels): bool => $n->invoice->is($ada) && $channels === ['mail', 'database']);
});

it('generates terms starting within 14 days from the daily command', function (): void {
    Queue::fake();
    $this->travelTo('2026-08-02 02:00:00'); // the autumn term starts in 15 days

    $this->artisan('fees:generate-term-invoices')->assertSuccessful();
    expect(($this->invoices)())->toHaveCount(0);

    $this->travelTo('2026-08-03 02:00:00'); // 14 days
    $this->artisan('fees:generate-term-invoices')->assertSuccessful();
    expect(($this->invoices)())->toHaveCount(3);

    $this->artisan('fees:generate-term-invoices')->assertSuccessful();
    expect(($this->invoices)())->toHaveCount(3);
});

it('lets an admin generate a term now', function (): void {
    Queue::fake();

    actingInSchool($this->admin, $this->school)->post(route('fees.generate'), ['term' => $this->nextTerm->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', '3 invoices generated.');

    actingInSchool($this->admin, $this->school)->post(route('fees.generate'), ['term' => $this->nextTerm->id])
        ->assertSessionHas('status', fn (string $status): bool => str_starts_with($status, 'No new invoices'));

    actingInSchool($this->parent, $this->school)->post(route('fees.generate'), ['term' => $this->term->id])->assertForbidden();
});
