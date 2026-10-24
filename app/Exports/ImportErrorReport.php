<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\ImportRowOutcome;
use App\Models\ImportRow;
use App\Support\StudentSheet;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

/**
 * The failed rows as they were uploaded, with the row number in front and the errors at the
 * end. Both extra columns are ignored by the import, so the fixed report can be uploaded again.
 *
 * @implements WithMapping<ImportRow>
 */
final class ImportErrorReport extends StringValueBinder implements FromQuery, WithCustomValueBinder, WithHeadings, WithMapping
{
    public function __construct(private readonly int $batchId) {}

    /**
     * @return Builder<ImportRow>
     */
    public function query(): Builder
    {
        return ImportRow::query()
            ->where('import_batch_id', $this->batchId)
            ->where('outcome', ImportRowOutcome::Failed)
            ->orderBy('row_number');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['row', ...StudentSheet::headings(), 'errors'];
    }

    /**
     * @param  ImportRow  $row
     * @return list<string|int|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->row_number,
            ...array_map(fn (string $column): ?string => $row->values[$column] ?? null, StudentSheet::headings()),
            implode(' ', $row->errors ?? []),
        ];
    }
}
