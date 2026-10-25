<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolInvitation;
use App\Support\CurrentSchool;
use App\Support\LocalDate;
use App\Support\LocalNumber;
use App\Support\PdfFont;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;

/*
 * Server-side texts follow the reader: the user's own language, else the school's
 * (SetLocale). Queued work runs in the school's language (CurrentSchool::runInSchoolLocale).
 */

beforeEach(function (): void {
    [$this->school] = setUpSchool();
    $this->school->update(['locale' => Locale::German]);
});

it('says what happened in the school\'s language', function (): void {
    $admin = memberOf($this->school, Role::SchoolAdmin);

    actingInSchool($admin, $this->school)
        ->post(route('subjects.store'), ['name' => 'Physik', 'code' => 'PH'])
        ->assertSessionHas('status', 'Fach Physik hinzugefügt.');
});

it('says it in the user\'s own language when they chose one', function (): void {
    $admin = memberOf($this->school, Role::SchoolAdmin, ['locale' => 'hi']);

    actingInSchool($admin, $this->school)
        ->post(route('subjects.store'), ['name' => 'Physics', 'code' => 'PH'])
        ->assertSessionHas('status', 'विषय Physics जोड़ा गया।');
});

it('translates validation messages and field names', function (): void {
    $admin = memberOf($this->school, Role::SchoolAdmin);

    actingInSchool($admin, $this->school)
        ->post(route('subjects.store'), ['name' => '', 'code' => 'PH'])
        ->assertSessionHasErrors(['name' => 'Name muss ausgefüllt werden.']);
});

it('runs queued work in the school\'s language and restores the language afterwards', function (): void {
    App::setLocale('en');

    $inside = app(CurrentSchool::class)->runInSchoolLocale($this->school, fn (): string => __('Subject saved.'));

    expect($inside)->toBe('Fach gespeichert.')
        ->and(App::getLocale())->toBe('en');
});

it('sends invitations in the invitee\'s language, else the school\'s', function (): void {
    $invitee = User::factory()->create(['locale' => null]);
    $membership = Membership::factory()->for($this->school)->for($invitee)->invited()->create();

    expect((new SchoolInvitation($membership))->locale)->toBe('de');

    $invitee->update(['locale' => 'hi']);

    expect((new SchoolInvitation($membership->fresh()))->locale)->toBe('hi');
});

it('writes dates and numbers the way each language does', function (string $locale, string $date, string $percent): void {
    App::setLocale($locale);
    $day = CarbonImmutable::parse('2026-10-26');

    expect(LocalDate::long($day))->toBe($date)
        ->and(LocalNumber::percent('86.50', 2))->toBe($percent);
})->with([
    'English (British)' => ['en', '26 October 2026', '86.5 %'],
    'German' => ['de', '26. Oktober 2026', '86,5 %'],
]);

it('gives Hindi PDFs a font with Devanagari glyphs', function (): void {
    App::setLocale('hi');
    $css = PdfFont::css();

    expect($css)->toContain('Noto Sans Devanagari')
        ->and(resource_path('fonts/NotoSansDevanagari-Regular.ttf'))->toBeFile()
        ->and(resource_path('fonts/NotoSansDevanagari-Bold.ttf'))->toBeFile();

    App::setLocale('de');

    expect(PdfFont::css())->not->toContain('Devanagari');
});

it('renders the report card PDF in the school\'s language for any reader', function (): void {
    $setup = setUpReportCards();
    $school = $setup['school'];
    $school->update(['locale' => Locale::German]);
    $card = reportCardFor($school, $setup['students'][0], $setup['term'], $setup['class']);
    App::setLocale('en');

    $html = app(CurrentSchool::class)->run($school, fn (): string => app(CurrentSchool::class)->runInSchoolLocale(
        $school,
        fn (): string => view('pdf.report-card', [
            'school' => $school,
            'logo' => null,
            'report' => $card->data,
            'comment' => null,
            'approvedBy' => null,
            'approvedAt' => null,
            'isDraft' => true,
        ])->render(),
    ));

    expect($html)->toContain('<html lang="de">')
        ->and($html)->toContain('Zeugnis')
        ->and($html)->toContain('ENTWURF')
        ->and($html)->toContain('Noch nicht freigegeben');
});
