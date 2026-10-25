<?php

declare(strict_types=1);

namespace App\Actions\Family;

use App\Enums\ReportCardStatus;
use App\Models\ReportCard;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * A child's published report cards, newest first. Drafts and approved cards that have not
 * been published yet stay with the school (ADR 0007).
 */
final class ListPublishedReportCards
{
    /**
     * The query, for the API to paginate.
     *
     * @return Builder<ReportCard>
     */
    public function query(Student $student): Builder
    {
        return ReportCard::query()
            ->where('student_id', $student->id)
            ->where('status', ReportCardStatus::Published)
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * @return Collection<int, ReportCard>
     */
    public function handle(Student $student): Collection
    {
        return $this->query($student)->get();
    }
}
