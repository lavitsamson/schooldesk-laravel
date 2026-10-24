<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Imports\StartStudentImport;
use App\Enums\ImportRowOutcome;
use App\Exports\StudentImportTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\StoreStudentImportRequest;
use App\Http\Resources\ImportBatchResource;
use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\StudentSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    /**
     * Failed rows shown on the progress page; all of them are in the error report.
     */
    public const SHOWN_FAILURES = 100;

    public function index(): Response
    {
        Gate::authorize('viewAny', ImportBatch::class);

        $batches = ImportBatch::query()
            ->with('user')
            ->withCount(ImportBatch::outcomeCounts())
            ->latest()
            ->latest('id')
            ->paginate(15);

        return Inertia::render('Imports/Index', [
            'batches' => ImportBatchResource::collection($batches),
            'columns' => collect(StudentSheet::COLUMNS)
                ->map(fn (string $description, string $name): array => [
                    'name' => $name,
                    'required' => in_array($name, StudentSheet::REQUIRED, true),
                    'description' => $description,
                ])
                ->values()
                ->all(),
            'classes' => SchoolClass::query()
                ->whereIn('academic_year_id', AcademicYear::query()->current()->select('id'))
                ->orderBy('name')
                ->orderBy('section')
                ->get()
                ->map(fn (SchoolClass $class): string => $class->displayName())
                ->all(),
            'maxFileMb' => round((int) config('schooldesk.imports.max_file_kb') / 1024, 1),
        ]);
    }

    public function store(StoreStudentImportRequest $request, StartStudentImport $action): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $batch = $action->handle($user, $request->upload());

        return to_route('imports.show', $batch)->with('status', 'Import started.');
    }

    /**
     * The progress page polls this (partial reload of "batch" and "failures") until the
     * import has finished.
     */
    public function show(ImportBatch $importBatch): Response
    {
        Gate::authorize('view', $importBatch);

        $importBatch->load('user')->loadCount(ImportBatch::outcomeCounts());

        return Inertia::render('Imports/Show', [
            'batch' => ImportBatchResource::make($importBatch)->resolve(),
            'failures' => fn (): array => $importBatch->rows()
                ->where('outcome', ImportRowOutcome::Failed)
                ->orderBy('row_number')
                ->limit(self::SHOWN_FAILURES)
                ->get()
                ->map(fn (ImportRow $row): array => [
                    'row' => $row->row_number,
                    'admission_no' => $row->values['admission_no'] ?? null,
                    'name' => trim(($row->values['first_name'] ?? '').' '.($row->values['last_name'] ?? '')),
                    'errors' => $row->errors ?? [],
                ])
                ->all(),
            'shownFailures' => self::SHOWN_FAILURES,
        ]);
    }

    public function errorReport(ImportBatch $importBatch): StreamedResponse
    {
        Gate::authorize('view', $importBatch);

        $disk = Storage::disk(ImportBatch::DISK);
        abort_if($importBatch->error_report_path === null || ! $disk->exists($importBatch->error_report_path), 404);

        $name = pathinfo($importBatch->original_name, PATHINFO_FILENAME).'-errors.csv';

        return $disk->download($importBatch->error_report_path, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function template(Request $request, Excel $excel): BinaryFileResponse
    {
        Gate::authorize('create', ImportBatch::class);

        $xlsx = $request->query('format') === 'xlsx';

        return $excel->download(
            new StudentImportTemplate,
            $xlsx ? 'student-import-template.xlsx' : 'student-import-template.csv',
            $xlsx ? Excel::XLSX : Excel::CSV,
        );
    }
}
