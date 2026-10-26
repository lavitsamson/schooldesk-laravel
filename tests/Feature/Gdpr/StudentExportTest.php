<?php

declare(strict_types=1);

use App\Enums\ActivityArea;
use App\Enums\Role;
use App\Jobs\PrepareStudentExport;
use App\Models\Activity;
use App\Models\StudentExport;
use App\Notifications\StudentExportReady;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    $this->travelTo('2026-10-20 09:00:00');
    foreach (setUpStudentRecords() as $key => $value) {
        $this->{$key} = $value;
    }
});

/**
 * The entries of a stored export ZIP, name => contents.
 *
 * @return array<string, string>
 */
function exportEntries(StudentExport $export): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk(StudentExport::DISK)->path((string) $export->file_path));

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $entries[$name] = (string) $zip->getFromIndex($i);
    }
    $zip->close();

    return $entries;
}

it('queues the export and logs the request', function (): void {
    Queue::fake();

    actingInSchool($this->admin, $this->school)->post(route('students.export', $this->ada))
        ->assertRedirect()
        ->assertSessionHas('status', 'The export is being prepared. You will get an email with the download link.');

    $export = inSchool($this->school, fn () => StudentExport::query()->sole());
    expect($export->student_id)->toBe($this->ada->id)
        ->and($export->user_id)->toBe($this->admin->id)
        ->and($export->completed_at)->toBeNull();
    Queue::assertPushed(PrepareStudentExport::class, fn (PrepareStudentExport $job): bool => $job->schoolId === $this->school->id && $job->exportId === $export->id);

    $entry = inSchool($this->school, fn () => Activity::query()->where('log_name', ActivityArea::Gdpr->value)->sole());
    expect($entry->description)->toBe('exported')
        ->and($entry->student_id)->toBe($this->ada->id)
        ->and($entry->causer_id)->toBe($this->admin->id);
});

it('builds a ZIP of everything held about the student and emails a link that expires', function (): void {
    Notification::fake();

    actingInSchool($this->admin, $this->school)->post(route('students.export', $this->ada));

    $export = inSchool($this->school, fn () => StudentExport::query()->sole());
    expect($export->completed_at)->not->toBeNull()
        ->and($export->expires_at?->toDateTimeString())->toBe('2026-10-21 09:00:00');

    $entries = exportEntries($export);
    expect(array_keys($entries))->toEqualCanonicalizing([
        'student.json',
        'photo.jpg',
        "report-cards/autumn-term-{$this->card->id}.pdf",
        'invoices/SD-2026-000001.pdf',
        "receipts/SD-2026-000001-{$this->payment->id}.pdf",
    ])
        ->and($entries["report-cards/autumn-term-{$this->card->id}.pdf"])->toBe('report card pdf')
        ->and($entries['invoices/SD-2026-000001.pdf'])->toStartWith('%PDF');

    $data = json_decode($entries['student.json'], true);
    expect($data['student'])->toMatchArray(['admission_no' => 'S-1', 'first_name' => 'Ada', 'last_name' => 'Test', 'address' => 'Hauptstr. 1', 'has_photo' => true])
        ->and(collect($data['guardians'])->pluck('name')->all())->toBe(['Greta Gran', 'Paula Parent'])
        ->and($data['enrolments'][0]['class'])->toBe('Grade 7 A')
        ->and($data['attendance'])->toBe([['date' => '2026-10-05', 'status' => 'absent', 'note' => 'Flu']])
        ->and($data['marks'][0])->toMatchArray(['subject' => 'Mathematics', 'assessment' => 'Test 1', 'marks' => '18.00', 'max_marks' => 20, 'remarks' => 'Ada was unwell before the test.'])
        ->and($data['report_cards'][0])->toMatchArray(['term' => 'Autumn term', 'status' => 'published', 'teacher_comment' => 'Ada has worked steadily.'])
        ->and($data['invoices'][0])->toMatchArray(['number' => 'SD-2026-000001', 'total' => 120_000])
        ->and($data['invoices'][0]['payments'][0])->toMatchArray(['amount' => 50_000, 'method' => 'cash'])
        ->and(collect($data['history'])->pluck('event'))->toContain('created', 'updated', 'exported');

    $url = null;
    Notification::assertSentTo($this->admin, StudentExportReady::class, function (StudentExportReady $n) use (&$url): bool {
        $mail = $n->toMail($this->admin);
        $url = $mail->actionUrl;

        return $mail->subject === 'Data export ready: Ada Test';
    });

    actingInSchool($this->admin, $this->school)->get($url)
        ->assertOk()
        ->assertDownload('data-export-S-1-2026-10-20.zip');

    // The link expires with the export.
    $this->travel(25)->hours();
    actingInSchool($this->admin, $this->school)->get($url)->assertForbidden();
});

it('refuses a download once the export has expired, and needs a signed link', function (): void {
    $export = inSchool($this->school, fn () => StudentExport::factory()->for($this->school)->completed()->create(['student_id' => $this->ada->id]));
    $link = fn (): string => URL::temporarySignedRoute('student-exports.download', now()->addHour(), ['studentExport' => $export]);

    actingInSchool($this->admin, $this->school)->get($link())->assertOk();
    actingInSchool($this->admin, $this->school)->get(route('student-exports.download', $export))->assertForbidden();

    inSchool($this->school, fn () => $export->update(['expires_at' => now()->subMinute()]));
    actingInSchool($this->admin, $this->school)->get($link())->assertStatus(410);
});

it('is for data protection admins only', function (Role $role): void {
    Queue::fake();
    $user = memberOf($this->school, $role);
    $export = inSchool($this->school, fn () => StudentExport::factory()->for($this->school)->completed()->create(['student_id' => $this->ada->id]));

    actingInSchool($user, $this->school)->post(route('students.export', $this->ada))->assertForbidden();
    actingInSchool($user, $this->school)
        ->get(URL::temporarySignedRoute('student-exports.download', now()->addHour(), ['studentExport' => $export]))
        ->assertForbidden();

    Queue::assertNothingPushed();
})->with([Role::Teacher, Role::Parent]);

it('deletes expired exports and their files, and exports whose job never finished', function (): void {
    $export = inSchool($this->school, fn () => StudentExport::factory()->for($this->school)->completed()->create(['student_id' => $this->ada->id]));
    inSchool($this->school, fn () => StudentExport::factory()->for($this->school)->create(['student_id' => $this->ada->id]));

    $this->artisan('model:prune', ['--model' => [StudentExport::class]])->assertSuccessful();
    expect(StudentExport::query()->withoutGlobalScopes()->count())->toBe(2);

    $this->travel(25)->hours();
    $this->artisan('model:prune', ['--model' => [StudentExport::class]])->assertSuccessful();

    expect(StudentExport::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($export->filePath()))->toBeFalse();
});
