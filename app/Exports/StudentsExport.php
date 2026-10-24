<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Guardian;
use App\Models\Student;
use App\Support\StudentSheet;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * Selected students in the import format, so the file can be edited and imported again.
 * Cells are written as text: admission numbers like 0042 and dates stay as they are.
 *
 * @implements WithMapping<Student>
 */
final class StudentsExport extends StringValueBinder implements FromQuery, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping
{
    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(private readonly array $studentIds) {}

    /**
     * @return Builder<Student>
     */
    public function query(): Builder
    {
        return Student::query()
            ->whereKey($this->studentIds)
            ->with([
                'currentEnrollment.schoolClass',
                'guardians' => fn ($q) => $q->wherePivot('is_primary', true),
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return StudentSheet::headings();
    }

    /**
     * @param  Student  $row
     * @return list<string|null>
     */
    public function map(mixed $row): array
    {
        /** @var Guardian|null $guardian */
        $guardian = $row->guardians->first();

        return [
            $row->admission_no,
            $row->first_name,
            $row->last_name,
            $row->date_of_birth->toDateString(),
            $row->gender?->value,
            $row->currentEnrollment?->schoolClass->displayName(),
            $row->address,
            $guardian?->name,
            $guardian?->relationship->value,
            $guardian?->email,
            $guardian?->phone,
        ];
    }
}
