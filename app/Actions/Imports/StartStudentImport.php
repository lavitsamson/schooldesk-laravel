<?php

declare(strict_types=1);

namespace App\Actions\Imports;

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Imports\StudentsImport;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Throwable;

/**
 * Stores the upload on the private disk and queues the import: one job per chunk of rows on
 * the "imports" queue, followed by CompleteImport.
 */
final class StartStudentImport
{
    public const QUEUE = 'imports';

    public function __construct(
        private readonly Excel $excel,
        private readonly CurrentSchool $currentSchool,
        private readonly FailImport $failImport,
    ) {}

    public function handle(User $user, UploadedFile $file): ImportBatch
    {
        $schoolId = $this->currentSchool->id();
        $extension = Str::lower($file->getClientOriginalExtension());

        $batch = ImportBatch::query()->create([
            'user_id' => $user->id,
            'type' => ImportType::Students,
            'original_name' => Str::limit($file->getClientOriginalName(), 250),
            'file_path' => $file->storeAs("schools/{$schoolId}/imports", Str::uuid().'.'.$extension, ImportBatch::DISK) ?: null,
            'status' => ImportStatus::Pending,
        ]);

        try {
            // Reading the row count happens here, so a corrupt file fails right away.
            $pending = $this->excel->queueImport(
                new StudentsImport($batch->id, $schoolId),
                (string) $batch->file_path,
                ImportBatch::DISK,
                $extension === 'xlsx' ? Excel::XLSX : Excel::CSV,
            );

            if ($pending instanceof PendingDispatch) {
                $pending->allOnQueue(self::QUEUE);
            }

            // A pending dispatch is sent when it is destroyed: here, inside the try.
            unset($pending);
        } catch (Throwable $e) {
            report($e);

            // With the sync queue the whole import runs (and may fail) inside this call.
            if ($batch->refresh()->status === ImportStatus::Pending) {
                $this->failImport->handle($batch, FailImport::UNREADABLE);
            }
        }

        return $batch->refresh();
    }
}
