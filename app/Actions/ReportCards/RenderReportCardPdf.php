<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Models\ReportCard;
use App\Support\CurrentSchool;
use App\Support\PdfLogo;
use Barryvdh\DomPDF\Facade\Pdf;

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

        // A document of the school: in its language, as the AI comment draft is (ADR 0010).
        return $this->currentSchool->runInSchoolLocale($school, fn (): string => Pdf::loadView('pdf.report-card', [
            'school' => $school,
            'logo' => PdfLogo::dataUri($school->logo_path, ReportCard::DISK),
            'report' => $card->data,
            'comment' => $card->teacher_comment,
            'approvedBy' => $card->approver?->name,
            'approvedAt' => $card->approved_at,
            'isDraft' => $card->isDraft(),
        ])->setPaper('a4')->output());
    }
}
