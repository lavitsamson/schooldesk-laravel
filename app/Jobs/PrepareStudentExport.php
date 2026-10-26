<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Gdpr\BuildStudentExport;
use App\Models\StudentExport;
use App\Notifications\StudentExportReady;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Builds a student data export, then emails the admin who asked for it the download link.
 */
final class PrepareStudentExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $exportId,
    ) {}

    public function handle(CurrentSchool $currentSchool, BuildStudentExport $build): void
    {
        $currentSchool->runInSchoolLocale($this->schoolId, function () use ($build): void {
            $export = StudentExport::query()->with(['student', 'user'])->find($this->exportId);

            // Gone: the student was anonymised in the meantime.
            if ($export === null) {
                return;
            }

            if ($export->completed_at === null) {
                $build->handle($export);
            }

            $export->user?->notify(new StudentExportReady($export));
        });
    }
}
