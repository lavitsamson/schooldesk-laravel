<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * One "generate report cards for the class" run. The work is a Laravel job batch (one job per
 * student, visible in Horizon); this row ties it to a school, class and term, so the class
 * page can show its progress without trusting a batch id from the request.
 *
 * @property int $id
 * @property int $class_id
 * @property int $term_id
 * @property int|null $user_id
 * @property string|null $job_batch_id
 * @property int $total
 * @property Carbon $created_at
 */
#[Fillable(['school_id', 'class_id', 'term_id', 'user_id', 'job_batch_id', 'total'])]
class ReportCardBatch extends Model
{
    use BelongsToSchool;

    private ?Batch $jobBatch = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobBatch(): ?Batch
    {
        if ($this->jobBatch === null && $this->job_batch_id !== null) {
            $this->jobBatch = Bus::findBatch($this->job_batch_id);
        }

        return $this->jobBatch;
    }

    /**
     * A run whose job batch is gone (pruned) counts as finished.
     */
    public function isFinished(): bool
    {
        return $this->jobBatch()?->finished() ?? true;
    }

    /**
     * @return array{id: int, total: int, processed: int, failed: int, progress: int, is_finished: bool, started_at: string}
     */
    public function summary(): array
    {
        $batch = $this->jobBatch();
        $finished = $this->isFinished();
        $failed = $batch->failedJobs ?? 0;
        // A failed job stays "pending" in a job batch; here it counts as done.
        $done = $batch === null ? $this->total : $batch->processedJobs() + $failed;

        return [
            'id' => $this->id,
            'total' => $this->total,
            'processed' => $done,
            'failed' => $failed,
            'progress' => $finished || $this->total === 0 ? 100 : (int) min(99, floor($done / $this->total * 100)),
            'is_finished' => $finished,
            'started_at' => $this->created_at->toIso8601String(),
        ];
    }
}
