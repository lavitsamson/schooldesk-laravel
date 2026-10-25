<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The teacher signs the report card off: its snapshot is frozen as it is, and the PDF is
 * rendered and stored. This is the step that makes the teacher responsible for the comment,
 * including one that started as an AI draft. Parents see nothing until it is published.
 */
final class ApproveReportCard
{
    public function __construct(private readonly RenderReportCardPdf $render) {}

    public function handle(ReportCard $card, User $approver): ReportCard
    {
        if (! $card->isDraft()) {
            throw ValidationException::withMessages(['status' => __('This report card has already been approved.')]);
        }

        if ($card->teacher_comment === null) {
            throw ValidationException::withMessages(['comment' => __('Write a comment before approving the report card.')]);
        }

        return DB::transaction(function () use ($card, $approver): ReportCard {
            $card->fill([
                'status' => ReportCardStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
            $card->setRelation('approver', $approver);

            Storage::disk(ReportCard::DISK)->put($card->pdfPath(), $this->render->handle($card));
            $card->pdf_path = $card->pdfPath();
            $card->save();

            return $card;
        });
    }
}
