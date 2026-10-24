<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Actions\Guardians\LinkGuardian;
use App\Actions\Guardians\SaveGuardian;
use App\Actions\Students\SaveStudent;
use App\Enums\GuardianRelationship;
use App\Enums\ImportRowOutcome;
use App\Enums\ImportStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Imports one chunk of student rows. Valid rows are saved even when others fail; every row's
 * outcome is recorded, with the original values and errors for failed ones.
 *
 * The admission number is the key: a row for an existing student updates them, so uploading
 * the same file twice changes nothing the second time. Within one file, an admission number
 * may only appear once.
 *
 * Runs inside the chunk job's transaction: if anything unexpected fails, the whole chunk is
 * rolled back and retried.
 *
 * @phpstan-import-type StudentRowData from ValidateStudentRow
 */
final class ImportStudentRows
{
    public function __construct(
        private readonly ValidateStudentRow $validateRow,
        private readonly SaveStudent $saveStudent,
        private readonly SaveGuardian $saveGuardian,
        private readonly LinkGuardian $linkGuardian,
    ) {}

    /**
     * @param  array<int, array<array-key, mixed>>  $rows  keyed by row number in the file
     */
    public function handle(ImportBatch $batch, array $rows): void
    {
        $rows = array_filter($rows, fn (array $row): bool => array_filter($row, fn (mixed $cell): bool => $cell !== null && $cell !== '') !== []);

        if ($rows === []) {
            return;
        }

        if ($batch->status === ImportStatus::Pending) {
            $batch->update(['status' => ImportStatus::Processing, 'started_at' => now()]);
        }

        $classes = $this->currentClasses();
        $results = array_map(fn (array $row): array => $this->validateRow->handle($row, $classes), $rows);

        $keys = array_values(array_unique(array_filter(array_map(
            fn (array $result): ?string => self::key($result['values']['admission_no']),
            $results,
        ))));
        $existing = $this->existingStudents($keys);
        // Admission numbers already imported from earlier chunks of this file.
        $seen = $batch->rows()->whereIn('key', $keys)->where('outcome', '!=', ImportRowOutcome::Failed)
            ->pluck('row_number', 'key')->all();

        $records = [];
        $failed = 0;

        foreach ($results as $rowNumber => $result) {
            $key = self::key($result['values']['admission_no']);
            $student = $key === null ? null : $existing->get($key);
            $errors = [...$result['errors'], ...$this->conflicts($result, $student, $key === null ? null : ($seen[$key] ?? null))];

            if ($errors !== [] || $result['data'] === null) {
                $records[] = $this->record($batch, $rowNumber, $key, ImportRowOutcome::Failed, $result['values'], $errors);
                $failed++;

                continue;
            }

            $outcome = $student === null ? ImportRowOutcome::Created : ImportRowOutcome::Updated;
            $existing->put((string) $key, $this->save($student, $result['data']));
            $seen[(string) $key] = $rowNumber;
            $records[] = $this->record($batch, $rowNumber, $key, $outcome);
        }

        ImportRow::query()->fillAndInsert($records);

        ImportBatch::query()->whereKey($batch->id)->incrementEach([
            'processed_rows' => count($rows),
            'failed_rows' => $failed,
        ]);
    }

    /**
     * Admission numbers are compared case-insensitively, as MySQL's unique index does.
     */
    private static function key(?string $admissionNo): ?string
    {
        return $admissionNo === null ? null : mb_strtolower($admissionNo);
    }

    /**
     * @param  array{values: array<string, string|null>, data: StudentRowData|null, errors: list<string>}  $result
     * @return list<string>
     */
    private function conflicts(array $result, ?Student $student, ?int $seenInRow): array
    {
        $admissionNo = $result['values']['admission_no'];
        $classId = $result['data']['class_id'] ?? null;
        $conflicts = [];

        if ($seenInRow !== null) {
            $conflicts[] = "The admission number {$admissionNo} already appears in row {$seenInRow} of this file.";
        }

        if ($student?->trashed() === true) {
            $conflicts[] = "The admission number {$admissionNo} belongs to a deleted student. Admission numbers are never reused.";
        } elseif ($student !== null && $student->status !== StudentStatus::Active
            && $classId !== null && $classId !== $student->currentEnrollment?->class_id) {
            $conflicts[] = "{$student->fullName()} is no longer active ({$student->status->label()}). Make them active again before moving them to a class.";
        }

        return $conflicts;
    }

    /**
     * @param  StudentRowData  $data
     */
    private function save(?Student $student, array $data): Student
    {
        $attributes = [
            ...$data['student'],
            // Matching ignores case; the stored spelling of the admission number is kept.
            'admission_no' => $student->admission_no ?? $data['student']['admission_no'],
            'class_id' => $data['class_id'],
        ];

        $student = $this->saveStudent->handle($student, $attributes);
        $student->load('currentEnrollment');

        if ($data['guardian'] !== null) {
            $this->saveGuardian($student, $data['guardian']);
        }

        return $student;
    }

    /**
     * Finds the guardian by email (siblings share guardians) or by name among the student's
     * guardians, so a re-import does not add them twice. Empty relationship, email or phone
     * cells never clear what is stored; a new guardian without a relationship is a legal guardian.
     *
     * @param  array{name: string, relationship: string|null, email: string|null, phone: string|null}  $data
     */
    private function saveGuardian(Student $student, array $data): void
    {
        $email = $data['email'] === null ? null : mb_strtolower($data['email']);

        $guardian = ($email === null ? null : Guardian::query()->where('email', $email)->first())
            ?? $student->guardians()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first();

        $guardian = $this->saveGuardian->handle($guardian, [
            'name' => $data['name'],
            'relationship' => $data['relationship'] ?? $guardian?->relationship->value ?? GuardianRelationship::LegalGuardian->value,
            'email' => $email ?? $guardian?->email,
            'phone' => $data['phone'] ?? $guardian?->phone,
        ]);

        $this->linkGuardian->handle($student, $guardian);
    }

    /**
     * Including soft-deleted students: their admission numbers are taken for good.
     *
     * @param  list<string>  $keys
     * @return Collection<string, Student>
     */
    private function existingStudents(array $keys): Collection
    {
        if ($keys === []) {
            return new Collection;
        }

        return Student::query()
            ->withTrashed()
            ->with('currentEnrollment')
            ->whereIn(DB::raw('lower(admission_no)'), $keys)
            ->get()
            ->keyBy(fn (Student $student): string => mb_strtolower($student->admission_no))
            ->toBase();
    }

    /**
     * @return array<string, int>
     */
    private function currentClasses(): array
    {
        return SchoolClass::query()
            ->whereIn('academic_year_id', AcademicYear::query()->current()->select('id'))
            ->get()
            ->mapWithKeys(fn (SchoolClass $class): array => [ValidateStudentRow::classKey($class->displayName()) => $class->id])
            ->all();
    }

    /**
     * @param  array<string, string|null>|null  $values
     * @param  list<string>|null  $errors
     * @return array<string, mixed>
     */
    private function record(ImportBatch $batch, int $rowNumber, ?string $key, ImportRowOutcome $outcome, ?array $values = null, ?array $errors = null): array
    {
        return [
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'key' => $key === null ? null : mb_substr($key, 0, 100),
            'outcome' => $outcome,
            'values' => $values,
            'errors' => $errors,
        ];
    }
}
