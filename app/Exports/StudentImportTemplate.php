<?php

declare(strict_types=1);

namespace App\Exports;

use App\Support\StudentSheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The empty import template: just the headings. The import page explains each column.
 */
final class StudentImportTemplate implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return StudentSheet::headings();
    }
}
