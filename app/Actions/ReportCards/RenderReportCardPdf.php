<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Models\ReportCard;
use App\Support\CurrentSchool;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * The report card as a one-page A4 PDF (dompdf), from the stored snapshot only: school logo,
 * grades per subject, overall result, attendance, the teacher's comment and who approved it.
 * A draft is rendered as a preview with a "Draft" watermark and is never stored.
 */
final class RenderReportCardPdf
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(ReportCard $card): string
    {
        $school = $this->currentSchool->get();
        $card->loadMissing('approver');

        return Pdf::loadView('pdf.report-card', [
            'school' => $school,
            'logo' => $this->logo($school->logo_path),
            'report' => $card->data,
            'comment' => $card->teacher_comment,
            'approvedBy' => $card->approver?->name,
            'approvedAt' => $card->approved_at,
            'isDraft' => $card->isDraft(),
        ])->setPaper('a4')->output();
    }

    /**
     * The logo inlined as a data URI: dompdf cannot read the private disk, and remote
     * loading stays off.
     */
    private function logo(?string $path): ?string
    {
        $disk = Storage::disk(ReportCard::DISK);

        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path);

        return in_array($mime, ['image/png', 'image/jpeg'], true)
            ? "data:{$mime};base64,".base64_encode((string) $disk->get($path))
            : null;
    }
}
