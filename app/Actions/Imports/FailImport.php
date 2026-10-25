<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Models\ImportBatch;
use Illuminate\Support\Facades\Storage;

/**
 * The import stopped: the file could not be read, or a chunk failed on every attempt. Chunks
 * that finished are kept, which is safe because importing the file again is idempotent.
 */
final class FailImport
{
    public const UNREADABLE = 'The file could not be read. Save it as CSV (UTF-8) or Excel (.xlsx) and upload it again.'; // i18n

    public const STOPPED = 'The import stopped unexpectedly. Rows imported so far are kept; upload the file again to import the rest (rows already imported are only updated).'; // i18n

    public function handle(ImportBatch $batch, string $message = self::STOPPED): void
    {
        if ($batch->file_path !== null) {
            Storage::disk(ImportBatch::DISK)->delete($batch->file_path);
        }

        $batch->update([
            'status' => ImportStatus::Failed,
            'failure_message' => $message,
            'file_path' => null,
            'finished_at' => now(),
        ]);
    }
}
