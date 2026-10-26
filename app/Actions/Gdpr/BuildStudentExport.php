<?php

declare(strict_types=1);

namespace App\Actions\Gdpr;

use App\Actions\Fees\RenderInvoicePdf;
use App\Actions\Fees\StorePaymentReceipt;
use App\Actions\ReportCards\RenderReportCardPdf;
use App\Actions\Students\SaveStudent;
use App\Enums\ReportCardStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\StudentExport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Builds the ZIP of a student data export and stores it on the private disk: student.json
 * (CollectStudentData), the photo, and the PDFs the school issued about the student (approved
 * report cards, invoices, payment receipts). Run by the queued PrepareStudentExport job.
 */
final class BuildStudentExport
{
    public function __construct(
        private readonly CollectStudentData $collect,
        private readonly RenderReportCardPdf $renderReportCard,
        private readonly RenderInvoicePdf $renderInvoice,
        private readonly StorePaymentReceipt $storeReceipt,
    ) {}

    public function handle(StudentExport $export): void
    {
        $student = $export->student;
        $tmp = sys_get_temp_dir().'/student-export-'.Str::uuid().'.zip';
        $zip = new ZipArchive;

        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('The export ZIP could not be created.');
        }

        try {
            $zip->addFromString('student.json', json_encode(
                $this->collect->handle($student),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            $disk = Storage::disk(StudentExport::DISK);

            if ($student->photo_path !== null && Storage::disk(SaveStudent::PHOTO_DISK)->exists($student->photo_path)) {
                $zip->addFromString('photo.'.pathinfo($student->photo_path, PATHINFO_EXTENSION), (string) Storage::disk(SaveStudent::PHOTO_DISK)->get($student->photo_path));
            }

            // Drafts are work in progress, not documents the school issued.
            ReportCard::query()->where('student_id', $student->id)->where('status', '!=', ReportCardStatus::Draft)->with('term')->each(
                function (ReportCard $card) use ($zip): void {
                    $stored = $card->pdf_path !== null ? Storage::disk(ReportCard::DISK)->get($card->pdf_path) : null;
                    $zip->addFromString('report-cards/'.Str::slug($card->term->name)."-{$card->id}.pdf", $stored ?? $this->renderReportCard->handle($card));
                },
            );

            Invoice::query()->where('student_id', $student->id)->with('payments')->each(function (Invoice $invoice) use ($zip, $disk): void {
                $zip->addFromString("invoices/{$invoice->number}.pdf", $this->renderInvoice->handle($invoice));

                $invoice->payments->each(function (Payment $payment) use ($zip, $disk, $invoice): void {
                    $path = $payment->receipt_path !== null && $disk->exists($payment->receipt_path)
                        ? $payment->receipt_path
                        : $this->storeReceipt->handle($payment->setRelation('invoice', $invoice));
                    $zip->addFromString("receipts/{$invoice->number}-{$payment->id}.pdf", (string) $disk->get($path));
                });
            });

            $zip->close();

            $stream = fopen($tmp, 'rb') ?: throw new RuntimeException('The export ZIP could not be read.');
            $disk->writeStream($export->filePath(), $stream);
            fclose($stream);
        } finally {
            @unlink($tmp);
        }

        $export->update([
            'file_path' => $export->filePath(),
            'completed_at' => now(),
            'expires_at' => now()->addHours((int) config('schooldesk.gdpr.export_link_hours')),
        ]);
    }
}
