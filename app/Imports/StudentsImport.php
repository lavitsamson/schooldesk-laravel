<?php

declare(strict_types=1);

namespace App\Imports;

use App\Actions\Imports\CompleteImport;
use App\Actions\Imports\FailImport;
use App\Actions\Imports\ImportStudentRows;
use App\Actions\Imports\StartStudentImport;
use App\Models\ImportBatch;
use App\Support\CurrentSchool;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use Spatie\Activitylog\Support\CauserResolver;

/**
 * Adapter between Laravel Excel's queued chunk reading and the import actions. It is
 * serialised into every chunk job, so it only carries ids; each job sets the school context
 * itself (jobs never inherit it from the request).
 *
 * Headings are slugged ("Admission no" → admission_no). CSV cells are bound as strings, so
 * admission numbers like 0042 keep their leading zeros. Only the first sheet is read.
 */
#[Queue(StartStudentImport::QUEUE)]
final class StudentsImport extends StringValueBinder implements ShouldQueue, ToCollection, WithChunkReading, WithCustomValueBinder, WithEvents, WithHeadingRow, WithMultipleSheets
{
    use RemembersChunkOffset;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly int $batchId,
        public readonly int $schoolId,
    ) {}

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function chunkSize(): int
    {
        return (int) config('schooldesk.imports.chunk_size');
    }

    /**
     * @param  Collection<int, Collection<array-key, mixed>>  $collection
     */
    public function collection(Collection $collection): void
    {
        // Rows of a chunk arrive in file order, starting at the chunk offset (row 2 is the first
        // data row below the headings).
        $first = $this->getChunkOffset() ?? 2;
        $rows = [];
        foreach ($collection->values() as $index => $row) {
            $rows[$first + $index] = $row->all();
        }

        // The audit log names the admin who uploaded the file as the author of the changes.
        $this->inSchool(fn (ImportBatch $batch) => app(CauserResolver::class)->withCauser(
            $batch->user()->first(),
            fn () => app(ImportStudentRows::class)->handle($batch, $rows),
        ));
    }

    /**
     * @return array<class-string, Closure>
     */
    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn (BeforeImport $event) => $this->inSchool(function (ImportBatch $batch) use ($event): void {
                $rows = array_values($event->getReader()->getTotalRows())[0] ?? 1;
                $batch->update(['total_rows' => max(0, $rows - 1)]);
            }),
            AfterImport::class => fn () => $this->inSchool(fn (ImportBatch $batch) => app(CompleteImport::class)->handle($batch)),
            ImportFailed::class => fn () => $this->inSchool(fn (ImportBatch $batch) => app(FailImport::class)->handle($batch)),
        ];
    }

    /**
     * @param  callable(ImportBatch): mixed  $callback
     */
    private function inSchool(callable $callback): void
    {
        app(CurrentSchool::class)->runInSchoolLocale(
            $this->schoolId,
            fn () => $callback(ImportBatch::query()->findOrFail($this->batchId)),
        );
    }
}
