<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The columns of the student spreadsheet. Import template, import and export share them, so
 * an exported file can be edited and imported again (admission_no is the key).
 */
final class StudentSheet
{
    public const REQUIRED = ['admission_no', 'first_name', 'last_name', 'date_of_birth'];

    /**
     * @var array<string, string>
     */
    public const COLUMNS = [
        'admission_no' => 'Required. Unique per school; an existing student with this number is updated.',
        'first_name' => 'Required.',
        'last_name' => 'Required.',
        'date_of_birth' => 'Required. YYYY-MM-DD or DD.MM.YYYY (Excel date cells work too).',
        'gender' => 'Optional. Female, male or diverse.',
        'class' => 'Optional. A class of the current academic year, e.g. "Grade 7 A".',
        'address' => 'Optional.',
        'guardian_name' => 'Optional. Added to the student; the first guardian becomes the primary contact.',
        'guardian_relationship' => 'Optional. Mother, father, grandparent, guardian or other (default: guardian).',
        'guardian_email' => 'Optional. Guardians with the same email are shared between siblings.',
        'guardian_phone' => 'Optional.',
    ];

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return array_keys(self::COLUMNS);
    }
}
