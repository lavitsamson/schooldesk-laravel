<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Models\ReportCard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF response of a report card: the stored file once approved, or a watermarked preview
 * of a draft rendered on the fly. Callers authorise first (ReportCardPolicy::download).
 */
final class DownloadReportCardPdf
{
    public function __construct(private readonly RenderReportCardPdf $render) {}

    public function handle(ReportCard $reportCard): Response
    {
        $name = Str::slug('report-card '.$reportCard->data['student']['first_name'].' '.$reportCard->data['student']['last_name'].' '.$reportCard->data['term']['name']).'.pdf';
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'];
        $disk = Storage::disk(ReportCard::DISK);

        if ($reportCard->pdf_path !== null && $disk->exists($reportCard->pdf_path)) {
            return $disk->download($reportCard->pdf_path, $name, $headers);
        }

        return response($this->render->handle($reportCard), 200, [
            ...$headers,
            'Content-Disposition' => 'inline; filename="draft-'.$name.'"',
        ]);
    }
}
