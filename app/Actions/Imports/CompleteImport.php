<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportRowOutcome;
use App\Enums\ImportStatus;
use App\Exports\ImportErrorReport;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;

/**
 * Runs after the last chunk: writes the error report (if any row failed) and deletes the
 * uploaded file, which is no longer needed.
 */
final class CompleteImport
{
    public function __construct(private readonly Excel $excel) {}

    public function handle(ImportBatch $batch): void
    {
        $reportPath = null;

        // XLSX, not CSV: the report repeats uploaded values, and only a typed file keeps a cell
        // like "=HYPERLINK(…)" as text when it is opened in Excel (docs/SECURITY.md).
        if ($batch->rows()->where('outcome', ImportRowOutcome::Failed)->exists()) {
            $reportPath = "schools/{$batch->school_id}/imports/{$batch->id}-errors.xlsx";
            $this->excel->store(new ImportErrorReport($batch->id), $reportPath, ImportBatch::DISK, Excel::XLSX);
        }

        if ($batch->file_path !== null) {
            Storage::disk(ImportBatch::DISK)->delete($batch->file_path);
        }

        $batch->refresh()->update([
            'status' => ImportStatus::Completed,
            // The row count read up front can include trailing empty rows.
            'total_rows' => $batch->processed_rows,
            'error_report_path' => $reportPath,
            'file_path' => null,
            'started_at' => $batch->started_at ?? now(),
            'finished_at' => now(),
        ]);
    }
}
