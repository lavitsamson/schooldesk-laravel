<?php

declare(strict_types=1);

namespace App\Actions\AuditLog;

use App\Http\Requests\AuditLog\ListActivitiesRequest;
use App\Models\Activity;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\ReportCard;
use App\Models\User;
use App\Support\CurrentSchool;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The school's audit log, newest first, with what each entry needs to be described: the
 * author, the student and the record (with the relations its label is built from).
 *
 * @phpstan-import-type ActivityFilters from ListActivitiesRequest
 */
final class ListActivities
{
    public const PER_PAGE = 50;

    public function __construct(private readonly CurrentSchool $currentSchool) {}

    /**
     * @param  ActivityFilters  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $timezone = $this->currentSchool->get()->timezone;

        return Activity::query()
            ->with([
                'causer',
                'student',
                'subject' => fn (MorphTo $morph) => $morph->morphWith([
                    Grade::class => ['assessment.subject'],
                    Payment::class => ['invoice'],
                    ReportCard::class => ['term'],
                ]),
            ])
            ->when($filters['area'] !== null, fn (Builder $q) => $q->where('log_name', $filters['area']))
            ->when($filters['user_id'] !== null, fn (Builder $q) => $q
                ->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', $filters['user_id']))
            ->when($filters['student_id'] !== null, fn (Builder $q) => $q->where('student_id', $filters['student_id']))
            ->when($filters['from'] !== null, fn (Builder $q) => $q->where(
                'created_at', '>=', CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()->utc(),
            ))
            ->when($filters['to'] !== null, fn (Builder $q) => $q->where(
                'created_at', '<', CarbonImmutable::parse($filters['to'], $timezone)->addDay()->startOfDay()->utc(),
            ))
            // Matches the (school_id, created_at) index; id orders entries of the same second.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
