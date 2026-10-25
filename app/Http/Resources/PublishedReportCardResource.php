<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ReportCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * A published report card as parents see it, from its approved snapshot. The PDF link is the
 * API endpoint (bearer token) for API calls, and a signed link that expires for the portal.
 *
 * @mixin ReportCard
 */
class PublishedReportCardResource extends JsonResource
{
    /**
     * Signed portal links stay valid this long.
     */
    private const LINK_MINUTES = 30;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'term' => ['id' => $this->data['term']['id'], 'name' => $this->data['term']['name']],
            'class' => $this->data['class']['name'] ?? null,
            'overall' => $this->data['overall'],
            'attendance_rate' => $this->data['attendance']['rate'],
            'comment' => $this->teacher_comment,
            'published_at' => $this->published_at?->toIso8601String(),
            'pdf_url' => $request->is('api/*')
                ? route('api.v1.report-cards.pdf', $this->resource)
                : URL::temporarySignedRoute('report-cards.pdf', now()->addMinutes(self::LINK_MINUTES), ['reportCard' => $this->resource]),
        ];
    }
}
