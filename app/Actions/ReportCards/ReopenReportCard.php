<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Takes an approved (not yet published) report card back to draft, to correct the comment or
 * recalculate it. The stored PDF is deleted, since it no longer matches.
 */
final class ReopenReportCard
{
    public function handle(ReportCard $card): ReportCard
    {
        if ($card->status !== ReportCardStatus::Approved) {
            throw ValidationException::withMessages(['status' => $card->isDraft()
                ? __('This report card is still a draft.')
                : __('A published report card cannot be reopened.')]);
        }

        if ($card->pdf_path !== null) {
            Storage::disk(ReportCard::DISK)->delete($card->pdf_path);
        }

        $card->update([
            'status' => ReportCardStatus::Draft,
            'pdf_path' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return $card;
    }
}
