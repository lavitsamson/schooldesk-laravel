<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReportCards\DownloadReportCardPdf;
use App\Http\Controllers\Controller;
use App\Models\ReportCard;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Children
 */
class ReportCardController extends Controller
{
    /**
     * Download a report card
     *
     * The PDF of a published report card of one's own child.
     *
     * @urlParam reportCard_id integer required The report card's id. Example: 31
     *
     * @response 200 scenario="The PDF" [binary application/pdf]
     * @response 403 {"message": "This action is unauthorized.", "code": "forbidden"}
     */
    public function pdf(ReportCard $reportCard, DownloadReportCardPdf $download): Response
    {
        Gate::authorize('viewAsGuardian', $reportCard);

        return $download->handle($reportCard);
    }
}
