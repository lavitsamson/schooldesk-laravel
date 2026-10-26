<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Enums\ActivityArea;
use App\Models\Activity;
use App\Models\AiCommentDraft;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\Student;
use App\Models\StudentExport;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * GDPR right to erasure (docs/GDPR.md). The student's personal data is replaced or deleted;
 * what the school must keep (marks, attendance, report-card results, invoices and payments)
 * stays, linked to a record nobody can identify any more:
 *
 * - name, admission number, date of birth, gender, address and photo are replaced or removed;
 * - mark remarks, attendance notes and report-card comments (free text about the child) are
 *   cleared, AI comment drafts deleted, report-card snapshots renamed;
 * - stored report-card PDFs and payment receipts are deleted (they are rendered again, with the
 *   anonymised name, if ever downloaded), and so are data exports;
 * - guardians are unlinked, and deleted unless they have other children at the school;
 * - in-app notifications about the student, import rows with their data, and personal fields
 *   in the audit log are deleted.
 *
 * The erasure itself is logged, by whom and when. It cannot be undone.
 */
final class AnonymiseStudent
{
    public const FIRST_NAME = 'Anonymised';

    public const LAST_NAME = 'Student';

    /**
     * Stands in for the date of birth, which the database requires.
     */
    public const DATE_OF_BIRTH = '1900-01-01';

    /**
     * Audit log fields that hold personal data, removed from the student's entries.
     */
    private const LOGGED_PERSONAL_FIELDS = ['admission_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'address', 'remarks'];

    public function handle(Student $student, User $user): void
    {
        $files = DB::transaction(function () use ($student, $user): array {
            $student = Student::query()->lockForUpdate()->findOrFail($student->id);
            $files = [$student->photo_path];

            $cards = ReportCard::query()->where('student_id', $student->id)->get();
            $files = [...$files, ...$cards->pluck('pdf_path')->all()];
            $files = [...$files, ...$this->receiptPaths($student)];
            $files = [...$files, ...StudentExport::query()->where('student_id', $student->id)->pluck('file_path')->all()];

            $this->deleteNotifications($student);
            $this->deleteImportRows($student->admission_no);
            $this->scrubAuditLog($student);

            // Query updates: they bypass the audit log, which must not record the old values.
            Student::query()->whereKey($student->id)->update([
                'admission_no' => "ANON-{$student->id}",
                'first_name' => self::FIRST_NAME,
                'last_name' => self::LAST_NAME,
                'date_of_birth' => self::DATE_OF_BIRTH,
                'gender' => null,
                'address' => null,
                'photo_path' => null,
                'anonymised_at' => now(),
            ]);

            Grade::query()->where('student_id', $student->id)->update(['remarks' => null]);
            AttendanceRecord::query()->where('student_id', $student->id)->update(['note' => null]);
            AiCommentDraft::query()->whereIn('report_card_id', $cards->modelKeys())->delete();

            foreach ($cards as $card) {
                $data = $card->data;
                $data['student'] = [...$data['student'], 'first_name' => self::FIRST_NAME, 'last_name' => self::LAST_NAME, 'admission_no' => "ANON-{$student->id}"];
                ReportCard::query()->whereKey($card->id)->update([
                    'data' => json_encode($data, JSON_THROW_ON_ERROR),
                    'teacher_comment' => null,
                    'pdf_path' => null,
                ]);
            }

            Payment::query()->whereIn('invoice_id', Invoice::query()->where('student_id', $student->id)->select('id'))->update(['receipt_path' => null]);
            StudentExport::query()->where('student_id', $student->id)->delete();

            $guardianIds = $student->guardians()->pluck('guardians.id')->all();
            $student->guardians()->detach();
            Guardian::query()->whereKey($guardianIds)->whereDoesntHave('students')->delete();

            activity(ActivityArea::Gdpr)
                ->performedOn($student->refresh())
                ->causedBy($user)
                ->event('anonymised')
                ->log('anonymised');

            return $files;
        });

        // After the commit: a rolled-back erasure keeps its files.
        Storage::disk(ReportCard::DISK)->delete(array_values(array_filter($files)));
    }

    /**
     * @return array<int, string|null>
     */
    private function receiptPaths(Student $student): array
    {
        /** @var array<int, string|null> */
        return Payment::query()
            ->whereIn('invoice_id', Invoice::query()->where('student_id', $student->id)->select('id'))
            ->pluck('receipt_path')
            ->all();
    }

    /**
     * Database notifications name the child (absences, report cards, invoices, payments). They
     * keep ids in a JSON text column, so they are matched here rather than in SQL.
     */
    private function deleteNotifications(Student $student): void
    {
        $invoiceIds = Invoice::query()->where('student_id', $student->id)->pluck('id')->all();

        $ids = DatabaseNotification::query()
            ->where('school_id', $student->getAttribute('school_id'))
            ->lazyById()
            ->filter(fn (DatabaseNotification $n): bool => ($n->data['student_id'] ?? null) === $student->id
                || in_array($n->data['invoice_id'] ?? null, $invoiceIds, true))
            ->map(fn (DatabaseNotification $n): string => $n->id)
            ->values()
            ->all();

        DatabaseNotification::query()->whereKey($ids)->delete();
    }

    /**
     * Import rows keep the uploaded values (names, dates of birth) until the batch is pruned.
     */
    private function deleteImportRows(string $admissionNo): void
    {
        ImportRow::query()
            ->whereIn('import_batch_id', ImportBatch::query()->select('id'))
            ->where('key', $admissionNo)
            ->delete();
    }

    private function scrubAuditLog(Student $student): void
    {
        Activity::query()->where('student_id', $student->id)->each(function (Activity $activity): void {
            $changes = $activity->attribute_changes?->all() ?? [];

            foreach (['attributes', 'old'] as $side) {
                if (is_array($changes[$side] ?? null)) {
                    $changes[$side] = array_diff_key($changes[$side], array_flip(self::LOGGED_PERSONAL_FIELDS));
                }
            }

            $activity->attribute_changes = collect($changes);
            $activity->save();
        });
    }
}
