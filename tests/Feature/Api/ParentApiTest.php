<?php

declare(strict_types=1);

use App\Actions\Members\GrantRole;
use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\ReportCard;
use App\Services\StripeService;
use Illuminate\Support\Str;
use Mockery\MockInterface;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 10:00:00');
    foreach (setUpFamily() as $key => $value) {
        $this->{$key} = $value;
    }
    [$this->ada, $this->ben, $this->cleo] = $this->students->all();
    $this->token = apiToken($this->parent, $this->school);
    $this->api = fn () => withApiToken($this->token);
});

it('lists the parent\'s children with cursor pagination', function (): void {
    ($this->api)()->getJson(route('api.v1.children.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0', [
            'id' => $this->ada->id,
            'first_name' => 'Ada',
            'last_name' => 'Test',
            'name' => 'Ada Test',
            'admission_no' => $this->ada->admission_no,
            'status' => 'active',
            'class' => ['id' => $this->class->id, 'name' => 'Grade 7 A'],
        ])
        ->assertJsonPath('data.1.first_name', 'Ben');

    $first = ($this->api)()->getJson(route('api.v1.children.index', ['limit' => 1]))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Ada')
        ->assertJsonPath('meta.per_page', 1);

    ($this->api)()->getJson(route('api.v1.children.index', ['limit' => 1, 'cursor' => $first->json('meta.next_cursor')]))
        ->assertJsonPath('data.0.first_name', 'Ben')
        ->assertJsonPath('meta.next_cursor', null);
});

it('validates the page size', function (int $limit): void {
    ($this->api)()->getJson(route('api.v1.children.index', ['limit' => $limit]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('limit');
})->with([0, 101]);

it('returns a child\'s attendance for a month', function (): void {
    AttendanceRecord::factory()->for($this->ada)->for($this->class, 'schoolClass')->create(['date' => '2026-09-14', 'status' => AttendanceStatus::Excused]);
    AttendanceRecord::factory()->for($this->ada)->for($this->class, 'schoolClass')->create(['date' => '2026-09-15', 'status' => AttendanceStatus::Present]);

    ($this->api)()->getJson(route('api.v1.children.attendance', ['student' => $this->ada, 'month' => '2026-09']))
        ->assertOk()
        ->assertJsonPath('data.month', '2026-09')
        ->assertJsonCount(30, 'data.days')
        ->assertJsonPath('data.days.13', ['date' => '2026-09-14', 'weekday' => 1, 'status' => 'excused'])
        ->assertJsonPath('data.totals', ['present' => 1, 'absent' => 0, 'late' => 0, 'excused' => 1])
        ->assertJsonPath('data.recorded_days', 2)
        ->assertJsonPath('data.attendance_rate', 50);

    ($this->api)()->getJson(route('api.v1.children.attendance', ['student' => $this->ada, 'month' => '2026-11']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.month.0', 'The month cannot be in the future.');
});

it('returns a child\'s grades in a term', function (): void {
    ($this->api)()->getJson(route('api.v1.children.grades', $this->ada))
        ->assertOk()
        ->assertJsonPath('data.student', ['id' => $this->ada->id, 'first_name' => 'Ada'])
        ->assertJsonPath('data.term.name', 'Autumn term')
        ->assertJsonPath('data.subjects.0.name', 'Mathematics')
        ->assertJsonPath('data.subjects.0.percentage', '90.00')
        ->assertJsonPath('data.subjects.0.assessments.0.name', 'Test 1')
        // Staff remarks are not part of the API.
        ->assertJsonMissingPath('data.subjects.0.assessments.0.remarks');

    ($this->api)()->getJson(route('api.v1.children.grades', ['student' => $this->ada, 'term' => $this->nextTerm->id]))
        ->assertJsonPath('data.term.name', 'Spring term');
    ($this->api)()->getJson(route('api.v1.children.grades', ['student' => $this->ada, 'term' => 999999]))
        ->assertNotFound()
        ->assertJson(['message' => 'Not found.', 'code' => 'not_found']);
});

it('lists published report cards and serves their PDF', function (): void {
    $published = ReportCard::factory()->for($this->class, 'schoolClass')->for($this->ada)->for($this->term)->published()->create();
    $approved = ReportCard::factory()->for($this->class, 'schoolClass')->for($this->ada)->for($this->nextTerm)->approved()->create();

    ($this->api)()->getJson(route('api.v1.children.report-cards', $this->ada))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonPath('data.0.term.name', 'Autumn term')
        ->assertJsonPath('data.0.pdf_url', route('api.v1.report-cards.pdf', $published));

    ($this->api)()->get(route('api.v1.report-cards.pdf', $published))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    ($this->api)()->getJson(route('api.v1.report-cards.pdf', $approved))
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

it('refuses another family\'s child', function (string $route): void {
    ($this->api)()->getJson(route($route, $this->cleo))
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
})->with(['api.v1.children.attendance', 'api.v1.children.grades', 'api.v1.children.report-cards']);

it('refuses another family\'s report card', function (): void {
    $cleos = ReportCard::factory()->for($this->class, 'schoolClass')->for($this->cleo)->for($this->term)->published()->create();

    ($this->api)()->getJson(route('api.v1.report-cards.pdf', $cleos))->assertForbidden();
});

it('lists the family\'s invoices', function (): void {
    $adas = Invoice::factory()->for($this->ada)->create(['total' => 120_000]);
    Invoice::factory()->for($this->cleo)->create();

    ($this->api)()->getJson(route('api.v1.invoices.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $adas->id)
        ->assertJsonPath('data.0.total', 120_000)
        ->assertJsonPath('data.0.balance', 120_000)
        ->assertJsonPath('data.0.currency', 'EUR');
});

it('starts Stripe Checkout for the parent\'s own open invoices', function (): void {
    $adas = Invoice::factory()->for($this->ada)->create();
    $cleos = Invoice::factory()->for($this->cleo)->create();

    $this->mock(StripeService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('createCheckoutSession')->once()->andReturn(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
    });

    ($this->api)()->postJson(route('api.v1.invoices.checkout-session', $adas))
        ->assertCreated()
        ->assertJsonPath('data.url', 'https://checkout.stripe.com/c/pay/cs_test_1');
    ($this->api)()->postJson(route('api.v1.invoices.checkout-session', $cleos))->assertForbidden();
});

it('lists the school\'s notifications and marks them read', function (): void {
    $notification = $this->parent->notifications()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'type' => 'report-card-published',
        'data' => ['report_card_id' => 5, 'student_id' => $this->ada->id, 'first_name' => 'Ada', 'term' => 'Autumn term'],
    ]);

    ($this->api)()->getJson(route('api.v1.notifications.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Ada\'s report card for the Autumn term has been published.')
        ->assertJsonPath('data.0.subject', ['type' => 'report_card', 'id' => 5, 'student_id' => $this->ada->id])
        ->assertJsonPath('data.0.read_at', null);

    ($this->api)()->postJson(route('api.v1.notifications.read', $notification->id))
        ->assertOk()
        ->assertJsonPath('data.read_at', now()->toIso8601String());
    ($this->api)()->postJson(route('api.v1.notifications.read', (string) Str::uuid()))->assertNotFound();
});

it('acts for the token\'s school only, also for a parent at two schools', function (): void {
    [$other] = setUpSchool();
    Membership::factory()->for($other)->for($this->parent)->create();
    app(GrantRole::class)->handle($this->parent, $other, Role::Parent);
    $otherToken = apiToken($this->parent, $other);

    withApiToken($otherToken)->getJson(route('api.v1.children.index'))->assertOk()->assertJsonCount(0, 'data');
    withApiToken($otherToken)->getJson(route('api.v1.children.attendance', $this->ada))->assertNotFound();
    withApiToken($this->token)->getJson(route('api.v1.children.attendance', $this->ada))->assertOk();
});

it('limits requests per token', function (): void {
    config(['schooldesk.api.requests_per_minute' => 2]);

    ($this->api)()->getJson(route('api.v1.me'))->assertOk()->assertHeader('X-RateLimit-Remaining', '1');
    ($this->api)()->getJson(route('api.v1.me'))->assertOk();
    ($this->api)()->getJson(route('api.v1.me'))
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');
});
