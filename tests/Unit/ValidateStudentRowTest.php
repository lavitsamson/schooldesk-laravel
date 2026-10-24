<?php

declare(strict_types=1);

use App\Actions\Imports\ValidateStudentRow;
use Carbon\CarbonImmutable;
use Tests\TestCase;

// The validator needs the application container, but no database.
uses(TestCase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array{values: array<string, string|null>, data: array<string, mixed>|null, errors: list<string>}
 */
function validateRow(array $overrides = [], array $classes = ['grade 7 a' => 7]): array
{
    return (new ValidateStudentRow)->handle([
        'admission_no' => 'S-1001',
        'first_name' => 'Lena',
        'last_name' => 'Fischer',
        'date_of_birth' => '2014-03-02',
        ...$overrides,
    ], $classes);
}

it('accepts a complete row', function (): void {
    $result = validateRow([
        'gender' => 'female',
        'class' => 'Grade 7 A',
        'address' => ' Hauptstr. 1 ',
        'guardian_name' => 'Anna Fischer',
        'guardian_relationship' => 'mother',
        'guardian_email' => 'anna@example.com',
        'guardian_phone' => '+49 69 1234',
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['data'])->toBe([
            'student' => [
                'admission_no' => 'S-1001',
                'first_name' => 'Lena',
                'last_name' => 'Fischer',
                'date_of_birth' => '2014-03-02',
                'gender' => 'female',
                'address' => 'Hauptstr. 1',
            ],
            'class_id' => 7,
            'guardian' => ['name' => 'Anna Fischer', 'relationship' => 'mother', 'email' => 'anna@example.com', 'phone' => '+49 69 1234'],
        ]);
});

it('reports every missing required field', function (): void {
    $result = validateRow(['admission_no' => '', 'first_name' => null, 'last_name' => '  ', 'date_of_birth' => null]);

    expect($result['data'])->toBeNull()
        ->and($result['errors'])->toBe([
            'The admission number field is required.',
            'The first name field is required.',
            'The last name field is required.',
            'The date of birth field is required.',
        ]);
});

it('reads dates in the accepted formats', function (mixed $cell, string $expected): void {
    expect(validateRow(['date_of_birth' => $cell])['data']['student']['date_of_birth'] ?? null)->toBe($expected);
})->with([
    'ISO' => ['2014-05-17', '2014-05-17'],
    'German' => ['17.05.2014', '2014-05-17'],
    'German, short' => ['7.5.2014', '2014-05-07'],
    'Excel date cell' => [41776, '2014-05-17'],
    'Excel date cell as float' => [41776.0, '2014-05-17'],
]);

it('rejects invalid and implausible dates', function (string $cell, string $error): void {
    expect(validateRow(['date_of_birth' => $cell])['errors'])->toBe([$error]);
})->with([
    'impossible day' => ['31.02.2014', 'The date of birth must be a date like 2014-05-17 or 17.05.2014.'],
    'US format' => ['05/17/2014', 'The date of birth must be a date like 2014-05-17 or 17.05.2014.'],
    'text' => ['soon', 'The date of birth must be a date like 2014-05-17 or 17.05.2014.'],
    'in the future' => [CarbonImmutable::tomorrow()->toDateString(), 'The date of birth field must be a date before today.'],
    'too old' => ['1899-12-31', 'The date of birth field must be a date after 1900-01-01.'],
]);

it('matches classes ignoring case and repeated spaces', function (): void {
    expect(validateRow(['class' => '  grade 7   A '])['data']['class_id'] ?? null)->toBe(7);
});

it('reports an unknown class', function (): void {
    expect(validateRow(['class' => 'Grade 13 Z'])['errors'])->toBe([
        'Unknown class "Grade 13 Z". Use the name of a class of the current academic year, e.g. "Grade 7 A".',
    ]);
});

it('accepts enum values, labels and German genders', function (string $cell, string $expected): void {
    expect(validateRow(['gender' => $cell])['data']['student']['gender'] ?? null)->toBe($expected);
})->with([
    ['male', 'male'],
    ['Female', 'female'],
    ['DIVERSE', 'diverse'],
    ['weiblich', 'female'],
    ['Männlich', 'male'],
]);

it('rejects an unknown gender', function (): void {
    expect(validateRow(['gender' => 'x'])['errors'])->toBe(['The gender must be female, male or diverse (or empty).']);
});

it('keeps admission numbers as text', function (mixed $cell, string $expected): void {
    expect(validateRow(['admission_no' => $cell])['data']['student']['admission_no'] ?? null)->toBe($expected);
})->with([
    'leading zeros' => ['0042', '0042'],
    'number cell' => [1001, '1001'],
    'whole float cell' => [1001.0, '1001'],
]);

it('rejects admission numbers with other characters', function (): void {
    expect(validateRow(['admission_no' => 'S 1001'])['errors'])
        ->toBe(['The admission number may only contain letters, digits and - _ . /']);
});

it('leaves an empty guardian relationship open and requires a guardian name', function (): void {
    // The importer keeps a stored relationship, or defaults a new guardian to "guardian".
    expect(validateRow(['guardian_name' => 'Anna Fischer'])['data']['guardian'] ?? [])->toHaveKey('relationship', null)
        ->and(validateRow(['guardian_email' => 'anna@example.com'])['errors'])
        ->toBe(['The guardian name is required when other guardian columns are filled.'])
        ->and(validateRow(['guardian_name' => 'Anna', 'guardian_email' => 'not-an-email'])['errors'])
        ->toBe(['The guardian email field must be a valid email address.']);
});

it('leaves optional student columns that are missing from the file out of the data', function (): void {
    $result = validateRow();

    expect($result['data']['student'] ?? [])->not->toHaveKeys(['gender', 'address'])
        ->and($result['data'])->toHaveKey('class_id', null)
        ->and($result['data'])->toHaveKey('guardian', null)
        ->and($result['values'])->toHaveKeys(['gender', 'address', 'class']);
});
