<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 10:00:00');
    foreach (setUpFamily() as $key => $value) {
        $this->{$key} = $value;
    }
    [$this->ada, $this->ben, $this->cleo] = $this->students->all();

    foreach (['2026-10-01' => AttendanceStatus::Present, '2026-10-02' => AttendanceStatus::Absent, '2026-10-05' => AttendanceStatus::Late, '2026-09-30' => AttendanceStatus::Present] as $date => $status) {
        AttendanceRecord::factory()->for($this->ada)->for($this->class, 'schoolClass')->create(['date' => $date, 'status' => $status]);
    }

    $this->asParent = fn () => actingInSchool($this->parent, $this->school);
});

it('shows parents their own children on the dashboard', function (): void {
    Invoice::factory()->for($this->ada)->create(['total' => 50_000, 'due_on' => '2026-10-01']);
    ReportCard::factory()->for($this->class, 'schoolClass')->for($this->ada)->for($this->term)->published()->create();

    ($this->asParent)()->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Family/Home')
            ->has('children', 2)
            ->where('children.0.name', 'Ada Test')
            ->where('children.0.class', 'Grade 7 A')
            // 1 Oct present, 2 Oct absent, 5 Oct late: 2 of 3 attended.
            ->where('children.0.attendance', ['month' => '2026-10', 'recorded' => 3, 'absent' => 1, 'late' => 1, 'rate' => 66.7])
            ->where('children.0.invoices', ['open' => 1, 'overdue' => 1, 'balance' => 50_000])
            ->where('children.0.report_card.term', 'Autumn term')
            ->where('children.1.name', 'Ben Test')
            ->where('children.1.report_card', null));
});

it('keeps the staff dashboard for staff, also when they are parents too', function (): void {
    actingInSchool($this->teacher, $this->school)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    app(GrantRole::class)->handle($this->teacher, $this->school, Role::Parent);
    actingInSchool($this->teacher, $this->school)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

it('shows a child\'s attendance as a month calendar', function (): void {
    ($this->asParent)()->get(route('family.attendance', $this->ada))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Family/Attendance')
            ->where('child.name', 'Ada Test')
            ->has('children', 2)
            ->where('attendance.month', '2026-10')
            ->where('attendance.previous_month', '2026-09')
            ->where('attendance.next_month', null)
            ->has('attendance.days', 31)
            ->where('attendance.days.0', ['date' => '2026-10-01', 'weekday' => 4, 'status' => 'present'])
            ->where('attendance.days.1.status', 'absent')
            ->where('attendance.days.2.status', null)
            ->where('attendance.totals', ['present' => 1, 'absent' => 1, 'late' => 1, 'excused' => 0])
            ->where('attendance.attendance_rate', 66.7));

    ($this->asParent)()->get(route('family.attendance', ['student' => $this->ada, 'month' => '2026-09']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('attendance.days', 30)
            ->where('attendance.next_month', '2026-10')
            ->where('attendance.recorded_days', 1));
});

it('refuses months in the future or in another format', function (string $month): void {
    ($this->asParent)()->get(route('family.attendance', ['student' => $this->ada, 'month' => $month]))
        ->assertSessionHasErrors('month');
})->with(['2026-11', '10/2026', '2026-13']);

it('shows a child\'s results so far in the term', function (): void {
    ($this->asParent)()->get(route('family.results', $this->ada))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Family/Results')
            ->where('term.name', 'Autumn term')
            ->has('terms', 2)
            ->where('report.student.first_name', 'Ada')
            ->where('report.subjects.0.name', 'Mathematics')
            ->where('report.subjects.0.percentage', '90.00'));

    ($this->asParent)()->get(route('family.results', ['student' => $this->ada, 'term' => $this->nextTerm->id]))
        ->assertInertia(fn (Assert $page) => $page->where('term.name', 'Spring term'));
});

it('lists only published report cards, with a signed PDF link', function (): void {
    $published = ReportCard::factory()->for($this->class, 'schoolClass')->for($this->ada)->for($this->term)->published()->create();
    ReportCard::factory()->for($this->class, 'schoolClass')->for($this->ada)->for($this->nextTerm)->approved()->create();

    $response = ($this->asParent)()->get(route('family.report-cards', $this->ada))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Family/ReportCards')
            ->has('reportCards', 1)
            ->where('reportCards.0.id', $published->id)
            ->where('reportCards.0.comment', 'A good term.'));

    $pdfUrl = $response->viewData('page')['props']['reportCards'][0]['pdf_url'];
    expect($pdfUrl)->toContain('signature=');

    ($this->asParent)()->get($pdfUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('lets parents download published report cards of their own children only', function (): void {
    $approved = ReportCard::factory()->for($this->class, 'schoolClass')->for($this->ada)->for($this->term)->approved()->create();
    $cleos = ReportCard::factory()->for($this->class, 'schoolClass')->for($this->cleo)->for($this->term)->published()->create();

    foreach ([$approved, $cleos] as $card) {
        ($this->asParent)()->get(URL::temporarySignedRoute('report-cards.pdf', now()->addMinutes(5), ['reportCard' => $card]))
            ->assertForbidden();
    }
});

it('shows the child pages to the child\'s parents only', function (string $route): void {
    $otherParent = memberOf($this->school, Role::Parent);

    ($this->asParent)()->get(route($route, $this->cleo))->assertForbidden();
    actingInSchool($otherParent, $this->school)->get(route($route, $this->ada))->assertForbidden();
    actingInSchool($this->classTeacher, $this->school)->get(route($route, $this->ada))->assertForbidden();
})->with(['family.attendance', 'family.results', 'family.report-cards']);

it('keeps a parent at two schools in the school they selected', function (): void {
    [$otherSchool] = setUpSchool();
    Membership::factory()->for($otherSchool)->for($this->parent)->create();
    app(GrantRole::class)->handle($this->parent, $otherSchool, Role::Parent);

    actingInSchool($this->parent, $otherSchool)->get(route('family.attendance', $this->ada))->assertNotFound();
    actingInSchool($this->parent, $otherSchool)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('Family/Home')->has('children', 0));
});

it('does not let a parent who left the school in', function (): void {
    $this->parent->memberships()->update(['status' => 'invited']);

    actingInSchool($this->parent, $this->school)->get(route('family.attendance', $this->ada))->assertRedirect(route('schools.select'));
});

it('is not a page for users without the parent role', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('family.attendance', $this->ada))->assertRedirect(route('schools.select'));
});
