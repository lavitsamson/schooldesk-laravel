<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Http\Requests\Guardians\SaveGuardianRequest;
use App\Models\Student;
use App\Support\StudentSheet;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Stringable;

/**
 * Checks one spreadsheet row on its own: required fields, formats and the class name. Checks
 * that need other rows or the database (duplicates, deleted students) are in ImportStudentRows.
 *
 * Optional student columns that are missing from the file entirely are left out of the data,
 * so importing a file without e.g. an address column keeps the addresses already stored.
 *
 * @phpstan-type StudentRowData array{
 *     student: array{admission_no: string, first_name: string, last_name: string, date_of_birth: string, gender?: string|null, address?: string|null},
 *     class_id: int|null,
 *     guardian: array{name: string, relationship: string|null, email: string|null, phone: string|null}|null,
 * }
 */
final class ValidateStudentRow
{
    private const DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'j.n.Y'];

    /**
     * @param  array<array-key, mixed>  $row  cell values keyed by (slugged) column heading
     * @param  array<string, int>  $classes  classes of the current year: classKey(display name) => id
     * @return array{values: array<string, string|null>, data: StudentRowData|null, errors: list<string>}
     */
    public function handle(array $row, array $classes): array
    {
        $values = [];
        foreach (StudentSheet::headings() as $column) {
            $values[$column] = $this->normalise($row[$column] ?? null);
        }

        $dateOfBirth = $this->parseDate($row['date_of_birth'] ?? null);
        if ($dateOfBirth !== null) {
            $values['date_of_birth'] = $dateOfBirth;
        }

        $input = [
            ...$values,
            'gender' => $this->enumValue(Gender::class, $values['gender'], ['weiblich' => 'female', 'männlich' => 'male', 'divers' => 'diverse']),
            'guardian_relationship' => $this->enumValue(GuardianRelationship::class, $values['guardian_relationship']),
        ];

        $validator = Validator::make($input, $this->rules(), $this->messages(), $this->attributes());
        $errors = $validator->errors()->all();

        $classId = null;
        if ($values['class'] !== null) {
            $classId = $classes[self::classKey($values['class'])] ?? null;

            if ($classId === null) {
                $errors[] = "Unknown class \"{$values['class']}\". Use the name of a class of the current academic year, e.g. \"Grade 7 A\".";
            }
        }

        if ($errors !== []) {
            return ['values' => $values, 'data' => null, 'errors' => array_values($errors)];
        }

        return ['values' => $values, 'data' => $this->data($row, $input, $classId), 'errors' => []];
    }

    /**
     * Class names are matched ignoring case and repeated spaces: "grade 7  a" is "Grade 7 A".
     */
    public static function classKey(string $displayName): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($displayName)));
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @param  array<string, string|null>  $input  validated
     * @return StudentRowData
     */
    private function data(array $row, array $input, ?int $classId): array
    {
        $student = [
            'admission_no' => (string) $input['admission_no'],
            'first_name' => (string) $input['first_name'],
            'last_name' => (string) $input['last_name'],
            'date_of_birth' => (string) $input['date_of_birth'],
        ];

        foreach (['gender', 'address'] as $optional) {
            if (array_key_exists($optional, $row)) {
                $student[$optional] = $input[$optional];
            }
        }

        $guardian = $input['guardian_name'] === null ? null : [
            'name' => $input['guardian_name'],
            'relationship' => $input['guardian_relationship'],
            'email' => $input['guardian_email'],
            'phone' => $input['guardian_phone'],
        ];

        return ['student' => $student, 'class_id' => $classId, 'guardian' => $guardian];
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        $guardian = SaveGuardianRequest::guardianRules();

        return [
            'admission_no' => ['required', 'string', 'max:30', 'regex:'.Student::ADMISSION_NO_PATTERN],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            // bail: a date that doesn't parse gets one message, not three.
            'date_of_birth' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['nullable', 'required_with:guardian_email,guardian_phone,guardian_relationship', 'string', 'max:255'],
            'guardian_relationship' => ['nullable', Rule::enum(GuardianRelationship::class)],
            'guardian_email' => $guardian['email'],
            'guardian_phone' => $guardian['phone'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'admission_no.regex' => 'The admission number may only contain letters, digits and - _ . /',
            'date_of_birth.date_format' => 'The date of birth must be a date like 2014-05-17 or 17.05.2014.',
            'gender.enum' => 'The gender must be female, male or diverse (or empty).',
            'guardian_relationship.enum' => 'The guardian relationship must be mother, father, grandparent, guardian or other.',
            'guardian_name.required_with' => 'The guardian name is required when other guardian columns are filled.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'admission_no' => 'admission number',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'date_of_birth' => 'date of birth',
            'guardian_name' => 'guardian name',
            'guardian_email' => 'guardian email',
            'guardian_phone' => 'guardian phone',
        ];
    }

    /**
     * Cells as trimmed strings (null when empty), as they are shown in the error report.
     */
    private function normalise(mixed $value): ?string
    {
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        if ($value instanceof Stringable || is_scalar($value)) {
            $value = trim((string) $value);

            return $value === '' ? null : $value;
        }

        return null;
    }

    /**
     * Text dates in the accepted formats, or Excel date cells (which arrive as serial numbers).
     */
    private function parseDate(mixed $value): ?string
    {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{4,5}(\.\d+)?$/', trim($value)) === 1)) {
            $serial = (float) $value;

            return $serial >= 1 && $serial < 2958466
                ? CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($serial))->toDateString()
                : null;
        }

        $value = $this->normalise($value);

        foreach (self::DATE_FORMATS as $format) {
            $date = $value === null ? false : DateTimeImmutable::createFromFormat('!'.$format, $value);

            // Round-trip check: rejects overflowing dates like 31.02.2014.
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * The enum value for a cell that holds either the value or the label (any case).
     *
     * @param  class-string<BackedEnum>  $enum
     * @param  array<string, string>  $aliases
     */
    private function enumValue(string $enum, ?string $cell, array $aliases = []): ?string
    {
        if ($cell === null) {
            return null;
        }

        $needle = mb_strtolower($cell);

        foreach ($enum::cases() as $case) {
            $label = method_exists($case, 'label') ? (string) $case->label() : '';

            if ($needle === (string) $case->value || $needle === mb_strtolower($label)) {
                return (string) $case->value;
            }
        }

        // Unknown values are passed on unchanged, so the enum rule reports them.
        return $aliases[$needle] ?? $cell;
    }
}
