<?php

declare(strict_types=1);

use App\Actions\Imports\FailImport;
use App\Enums\GuardianRelationship;
use App\Enums\ImportRowOutcome;
use App\Enums\ImportStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\StudentSheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Jobs\QueueImport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/*
 * The queue runs synchronously in tests, so an upload is imported completely inside the
 * request: every chunk job, then CompleteImport.
 */

beforeEach(function (): void {
    Storage::fake(ImportBatch::DISK);

    [$this->school, $this->year, $this->class] = setUpSchool();
    $this->admin = memberOf($this->school, Role::SchoolAdmin);
    $this->upload = fn (UploadedFile $file) => actingInSchool($this->admin, $this->school)
        ->post(route('imports.store'), ['file' => $file]);
});

/**
 * A CSV upload with the template headings and the given rows (cells in heading order).
 *
 * @param  list<list<string>>  $rows
 */
function studentCsv(array $rows, string $name = 'students.csv', string $delimiter = ','): UploadedFile
{
    $lines = array_map(
        fn (array $cells): string => implode($delimiter, array_map(
            fn (string $cell): string => str_contains($cell, $delimiter) || str_contains($cell, '"') ? '"'.str_replace('"', '""', $cell).'"' : $cell,
            $cells,
        )),
        [StudentSheet::headings(), ...$rows],
    );

    return UploadedFile::fake()->createWithContent($name, implode("\n", $lines)."\n");
}

/**
 * A row in template column order: admission_no, first_name, last_name, date_of_birth, gender,
 * class, address, guardian_name, guardian_relationship, guardian_email, guardian_phone.
 *
 * @param  array<string, string>  $overrides
 * @return list<string>
 */
function studentRow(string $admissionNo, array $overrides = []): array
{
    $row = [
        'admission_no' => $admissionNo,
        'first_name' => 'Lena',
        'last_name' => 'Fischer',
        'date_of_birth' => '2014-03-02',
        'gender' => 'female',
        'class' => 'Grade 7 A',
        'address' => 'Hauptstr. 1',
        'guardian_name' => '',
        'guardian_relationship' => '',
        'guardian_email' => '',
        'guardian_phone' => '',
        ...$overrides,
    ];

    return array_values(array_map(fn (string $column): string => $row[$column], StudentSheet::headings()));
}

function latestBatch(School $school): ImportBatch
{
    return inSchool($school, fn () => ImportBatch::query()->withCount(ImportBatch::outcomeCounts())->latest('id')->firstOrFail());
}

it('imports students with their class and guardian', function (): void {
    ($this->upload)(studentCsv([
        studentRow('S-1', ['guardian_name' => 'Anna Fischer', 'guardian_relationship' => 'Mother', 'guardian_email' => 'Anna@Example.com']),
        studentRow('S-2', ['first_name' => 'Max', 'gender' => '', 'class' => '', 'guardian_name' => 'Anna Fischer', 'guardian_email' => 'anna@example.com']),
    ]))->assertRedirect(route('imports.show', latestBatch($this->school)))->assertSessionHas('status', 'Import started.');

    $batch = latestBatch($this->school);

    expect($batch->status)->toBe(ImportStatus::Completed)
        ->and([$batch->total_rows, $batch->processed_rows, $batch->failed_rows, $batch->created_rows])->toBe([2, 2, 0, 2])
        ->and($batch->file_path)->toBeNull()
        ->and($batch->error_report_path)->toBeNull()
        ->and(Storage::disk(ImportBatch::DISK)->allFiles())->toBe([]);

    inSchool($this->school, function (): void {
        $lena = Student::query()->where('admission_no', 'S-1')->sole();
        $max = Student::query()->where('admission_no', 'S-2')->sole();
        $guardian = Guardian::query()->sole();

        expect($lena->date_of_birth->toDateString())->toBe('2014-03-02')
            ->and($lena->currentEnrollment?->class_id)->toBe($this->class->id)
            ->and($max->currentEnrollment)->toBeNull()
            ->and($max->gender)->toBeNull()
            // Siblings share the guardian found by email.
            ->and($guardian->email)->toBe('anna@example.com')
            ->and($guardian->relationship)->toBe(GuardianRelationship::Mother)
            ->and($guardian->students()->count())->toBe(2);
    });
});

it('changes nothing when the same file is uploaded again', function (): void {
    $file = fn () => studentCsv([
        studentRow('S-1', ['guardian_name' => 'Anna Fischer', 'guardian_phone' => '+49 69 1234']),
        studentRow('S-2', ['first_name' => 'Max']),
    ]);

    ($this->upload)($file());
    $before = inSchool($this->school, fn () => Student::query()->orderBy('id')->get()->map->only(['id', 'first_name', 'address'])->all());

    ($this->upload)($file());
    $batch = latestBatch($this->school);

    expect([$batch->created_rows, $batch->updated_rows, $batch->failed_rows])->toBe([0, 2, 0]);

    inSchool($this->school, function () use ($before): void {
        expect(Student::query()->orderBy('id')->get()->map->only(['id', 'first_name', 'address'])->all())->toBe($before)
            ->and(Guardian::query()->sole()->relationship)->toBe(GuardianRelationship::LegalGuardian)
            ->and(Enrollment::query()->count())->toBe(2);
    });
});

it('updates existing students by admission number, ignoring case', function (): void {
    $student = Student::factory()->for($this->school)->create(['admission_no' => 'S-1', 'first_name' => 'Old', 'address' => 'Kept']);

    // No address column: stored addresses are kept.
    $csv = "admission_no,first_name,last_name,date_of_birth\ns-1,New,Fischer,02.03.2014\n";
    ($this->upload)(UploadedFile::fake()->createWithContent('update.csv', $csv));

    expect(latestBatch($this->school)->updated_rows)->toBe(1);

    $student = inSchool($this->school, fn () => $student->fresh());
    expect($student?->first_name)->toBe('New')
        ->and($student?->admission_no)->toBe('S-1')
        ->and($student?->address)->toBe('Kept');
});

it('imports valid rows and reports the invalid ones', function (int $chunkSize): void {
    config(['schooldesk.imports.chunk_size' => $chunkSize]);
    Student::factory()->for($this->school)->create(['admission_no' => 'GONE'])->delete();

    ($this->upload)(studentCsv([
        studentRow('S-1'),
        studentRow('S-2', ['date_of_birth' => '31.02.2014']),
        studentRow('S-3', ['class' => 'Grade 13 Z']),
        studentRow('S-1', ['first_name' => 'Twin']),
        studentRow('GONE'),
        studentRow('S-4', ['last_name' => '', 'address' => 'Main St, 5']),
    ]));

    $batch = latestBatch($this->school);

    expect($batch->status)->toBe(ImportStatus::Completed)
        ->and([$batch->processed_rows, $batch->failed_rows, $batch->created_rows])->toBe([6, 5, 1])
        ->and(inSchool($this->school, fn () => Student::query()->pluck('admission_no')->all()))->toBe(['S-1']);

    $errors = inSchool($this->school, fn () => $batch->rows()->where('outcome', ImportRowOutcome::Failed)
        ->orderBy('row_number')->get()->mapWithKeys(fn (ImportRow $row): array => [$row->row_number => $row->errors])->all());

    expect($errors)->toBe([
        3 => ['The date of birth must be a date like 2014-05-17 or 17.05.2014.'],
        4 => ['Unknown class "Grade 13 Z". Use the name of a class of the current academic year, e.g. "Grade 7 A".'],
        5 => ['The admission number S-1 already appears in row 2 of this file.'],
        6 => ['The admission number GONE belongs to a deleted student. Admission numbers are never reused.'],
        7 => ['The last name field is required.'],
    ]);

    $report = actingInSchool($this->admin, $this->school)->get(route('imports.error-report', $batch))
        ->assertOk()
        ->assertDownload('students-errors.csv')
        ->streamedContent();

    $lines = array_map(str_getcsv(...), explode("\n", trim($report)));
    expect($lines)->toHaveCount(6)
        ->and($lines[0])->toBe(['row', ...StudentSheet::headings(), 'errors'])
        ->and($lines[1][0])->toBe('3')
        ->and($lines[1][4])->toBe('31.02.2014')
        ->and($lines[5][7])->toBe('Main St, 5')
        ->and(end($lines[5]))->toBe('The last name field is required.');
})->with(['one chunk' => 200, 'chunks of two rows' => 2]);

it('reads Excel files with date cells and number cells', function (): void {
    $sheet = new Spreadsheet;
    $sheet->getActiveSheet()->fromArray([StudentSheet::headings()]);
    $sheet->getActiveSheet()->fromArray([[42, 'Jonas', 'Weber', 41776]], null, 'A2');
    $sheet->getActiveSheet()->getStyle('D2')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    IOFactory::createWriter($sheet, 'Xlsx')->save($path);

    ($this->upload)(new UploadedFile($path, 'students.xlsx', null, null, true));

    $student = inSchool($this->school, fn () => Student::query()->sole());
    expect($student->admission_no)->toBe('42')
        ->and($student->date_of_birth->toDateString())->toBe('2014-05-17');
});

it('reads semicolon-separated CSV saved by German Excel (Windows-1252)', function (): void {
    $csv = "Admission No;First name;Last name;Date of birth;Gender;Address\n"
        ."0042;Jürgen;Müller;7.5.2014;männlich;Königstraße 3\n";

    ($this->upload)(UploadedFile::fake()->createWithContent('schueler.csv', (string) mb_convert_encoding($csv, 'Windows-1252', 'UTF-8')));

    $student = inSchool($this->school, fn () => Student::query()->sole());
    expect($student->only(['admission_no', 'first_name', 'last_name', 'address']))
        ->toBe(['admission_no' => '0042', 'first_name' => 'Jürgen', 'last_name' => 'Müller', 'address' => 'Königstraße 3'])
        ->and($student->gender?->value)->toBe('male');
});

it('rejects files without the required columns or of another type', function (UploadedFile $file, string $error): void {
    ($this->upload)($file)->assertSessionHasErrors(['file' => $error]);

    expect(inSchool($this->school, fn () => ImportBatch::query()->count()))->toBe(0);
})->with([
    'missing columns' => [
        fn () => UploadedFile::fake()->createWithContent('students.csv', "admission_no,name\nS-1,Lena\n"),
        'The first row must contain the column headings. Missing: first_name, last_name, date_of_birth. Download the template to see the format.',
    ],
    'PDF' => [fn () => UploadedFile::fake()->create('students.pdf', 10, 'application/pdf'), 'Upload a CSV or Excel (.xlsx) file.'],
    'broken Excel file' => [
        fn () => UploadedFile::fake()->createWithContent('students.xlsx', 'not a zip file'),
        'The file could not be read. Save it as CSV (UTF-8) or Excel (.xlsx) and try again.',
    ],
]);

it('queues the import on the imports queue', function (): void {
    Queue::fake();

    ($this->upload)(studentCsv([studentRow('S-1')]))->assertRedirect();

    Queue::assertPushedOn('imports', QueueImport::class);

    $batch = latestBatch($this->school);
    expect($batch->status)->toBe(ImportStatus::Pending)
        ->and($batch->total_rows)->toBe(1)
        ->and(Storage::disk(ImportBatch::DISK)->exists((string) $batch->file_path))->toBeTrue();
});

it('marks the import as failed when a chunk fails', function (): void {
    Student::creating(fn () => throw new RuntimeException('Database gone'));

    ($this->upload)(studentCsv([studentRow('S-1')]))->assertRedirect();

    $batch = latestBatch($this->school);
    expect($batch->status)->toBe(ImportStatus::Failed)
        ->and($batch->failure_message)->toBe(FailImport::STOPPED)
        ->and($batch->file_path)->toBeNull()
        ->and(Storage::disk(ImportBatch::DISK)->allFiles())->toBe([]);
});

it('shows the import page with columns, classes and recent imports', function (): void {
    ImportBatch::factory()->for($this->school)->create(['user_id' => $this->admin->id]);
    ImportBatch::factory()->create();

    actingInSchool($this->admin, $this->school)->get(route('imports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Index')
            ->has('batches.data', 1)
            ->where('batches.data.0.uploaded_by', $this->admin->name)
            ->has('columns', count(StudentSheet::COLUMNS))
            ->where('columns.0', ['name' => 'admission_no', 'required' => true, 'description' => StudentSheet::COLUMNS['admission_no']])
            ->where('classes', ['Grade 7 A'])
            ->where('maxFileMb', 5));
});

it('shows progress and the first failed rows', function (): void {
    ($this->upload)(studentCsv([studentRow('S-1'), studentRow('S-2', ['date_of_birth' => 'soon'])]));
    $batch = latestBatch($this->school);

    actingInSchool($this->admin, $this->school)->get(route('imports.show', $batch))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Imports/Show')
            ->where('batch.status', 'completed')
            ->where('batch.progress', 100)
            ->where('batch.created_rows', 1)
            ->where('batch.error_report_url', route('imports.error-report', $batch))
            ->where('failures', [[
                'row' => 3,
                'admission_no' => 'S-2',
                'name' => 'Lena Fischer',
                'errors' => ['The date of birth must be a date like 2014-05-17 or 17.05.2014.'],
            ]]));
});

it('returns 404 for an error report that does not exist', function (): void {
    $batch = ImportBatch::factory()->for($this->school)->create();

    actingInSchool($this->admin, $this->school)->get(route('imports.error-report', $batch))->assertNotFound();
});

it('offers the template as CSV and Excel', function (): void {
    $csv = actingInSchool($this->admin, $this->school)->get(route('imports.template'))
        ->assertOk()->assertDownload('student-import-template.csv');
    expect(trim((string) file_get_contents($csv->getFile()->getPathname())))->toBe(implode(',', array_map(fn ($h) => '"'.$h.'"', StudentSheet::headings())));

    actingInSchool($this->admin, $this->school)->get(route('imports.template', ['format' => 'xlsx']))
        ->assertOk()->assertDownload('student-import-template.xlsx');
});

it('is only for school admins', function (string $method, string $route): void {
    $teacher = memberOf($this->school, Role::Teacher);
    $batch = ImportBatch::factory()->for($this->school)->withErrorReport()->create();
    $uri = str_contains($route, 'show') || str_contains($route, 'error') ? route($route, $batch) : route($route);

    actingInSchool($teacher, $this->school)->{$method}($uri, ['file' => studentCsv([studentRow('S-1')])])->assertForbidden();
})->with([
    ['get', 'imports.index'],
    ['get', 'imports.template'],
    ['post', 'imports.store'],
    ['get', 'imports.show'],
    ['get', 'imports.error-report'],
]);

it('prunes old imports and their files', function (): void {
    $disk = Storage::disk(ImportBatch::DISK);
    $old = ImportBatch::factory()->for($this->school)->withErrorReport()->create(['created_at' => now()->subDays(31)]);
    $recent = ImportBatch::factory()->for($this->school)->withErrorReport()->create();
    $disk->put((string) $old->error_report_path, 'x');
    $disk->put((string) $recent->error_report_path, 'x');

    $this->artisan('model:prune', ['--model' => ImportBatch::class])->assertSuccessful();

    expect(ImportBatch::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$recent->id])
        ->and($disk->exists((string) $old->error_report_path))->toBeFalse()
        ->and($disk->exists((string) $recent->error_report_path))->toBeTrue();
});

it('exports selected students in the import format, which imports again unchanged', function (): void {
    $student = Student::factory()->for($this->school)->create(['admission_no' => '0042', 'address' => 'Hauptstr. 1, Frankfurt']);
    Enrollment::factory()->for($this->class, 'schoolClass')->for($student)->create();
    $guardian = Guardian::factory()->for($this->school)->create(['email' => 'anna@example.com']);
    $student->guardians()->attach($guardian, ['is_primary' => true]);
    Student::factory()->for($this->school)->create(['admission_no' => 'NOT-SELECTED']);

    $response = actingInSchool($this->admin, $this->school)
        ->post(route('students.bulk'), ['action' => 'export', 'student_ids' => [$student->id]])
        ->assertOk()
        ->assertDownload('students-'.now()->format('Y-m-d').'.xlsx');

    $rows = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->toArray();
    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(StudentSheet::headings())
        ->and(array_slice($rows[1], 0, 7))->toBe([
            '0042', $student->first_name, $student->last_name, $student->date_of_birth->toDateString(),
            $student->gender?->value, 'Grade 7 A', 'Hauptstr. 1, Frankfurt',
        ])
        ->and($rows[1][9])->toBe('anna@example.com');

    ($this->upload)(new UploadedFile($response->getFile()->getPathname(), 'students.xlsx', null, null, true));

    $batch = latestBatch($this->school);
    expect([$batch->updated_rows, $batch->failed_rows])->toBe([1, 0])
        ->and(inSchool($this->school, fn () => Guardian::query()->count()))->toBe(1);
});

it('does not move inactive students to a class', function (): void {
    $student = Student::factory()->for($this->school)->left()->create(['admission_no' => 'S-1']);
    $other = SchoolClass::factory()->for($this->school)->for($this->year)->create(['name' => 'Grade 8', 'section' => 'A']);

    ($this->upload)(studentCsv([studentRow('S-1', ['class' => 'Grade 8 A'])]));

    expect(latestBatch($this->school)->failed_rows)->toBe(1)
        ->and(inSchool($this->school, fn () => $student->fresh()?->status))->toBe(StudentStatus::Left)
        ->and(inSchool($this->school, fn () => Enrollment::query()->where('class_id', $other->id)->exists()))->toBeFalse();
});
