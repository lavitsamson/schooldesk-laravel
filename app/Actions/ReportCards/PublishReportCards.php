<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Enums\ReportCardStatus;
use App\Jobs\NotifyGuardiansOfReportCard;
use App\Models\ReportCard;
use App\Support\CurrentSchool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Publishes approved report cards to parents: they become visible in the parent portal and
 * each student's guardians are notified (queued, after the commit). Drafts are skipped, so
 * "publish all approved" on a half-finished class is safe. Returns how many were published.
 */
final class PublishReportCards
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @param  Collection<int, ReportCard>  $cards
     */
    public function handle(Collection $cards): int
    {
        $schoolId = $this->currentSchool->id();
        $approved = $cards->filter(fn (ReportCard $card): bool => $card->status === ReportCardStatus::Approved && $card->pdf_path !== null);

        DB::transaction(function () use ($approved, $schoolId): void {
            foreach ($approved as $card) {
                $card->update(['status' => ReportCardStatus::Published, 'published_at' => now()]);
                NotifyGuardiansOfReportCard::dispatch($schoolId, $card->id)->afterCommit();
            }
        });

        return $approved->count();
    }
}
