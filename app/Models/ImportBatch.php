<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportRowOutcome;
use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Scopes\SchoolScope;
use App\Policies\ImportBatchPolicy;
use Closure;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One uploaded file and its progress. Rows are processed in queued chunks.
 *
 * @property int $id
 * @property int|null $user_id
 * @property ImportType $type
 * @property string $original_name
 * @property string|null $file_path
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $failed_rows
 * @property string|null $error_report_path
 * @property string|null $failure_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property-read int|null $created_rows
 * @property-read int|null $updated_rows
 * @property-read User|null $user
 */
#[Fillable(['school_id', 'user_id', 'type', 'original_name', 'file_path', 'status', 'total_rows', 'processed_rows', 'failed_rows', 'error_report_path', 'failure_message', 'started_at', 'finished_at'])]
#[UsePolicy(ImportBatchPolicy::class)]
class ImportBatch extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    use Prunable;

    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ImportType::class,
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Imports and their error reports (which hold personal data) are kept for a limited time.
     * model:prune runs for the whole platform, outside any school context, hence no school scope.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->withoutGlobalScope(SchoolScope::class)
            ->where('created_at', '<', now()->subDays((int) config('schooldesk.imports.keep_days')));
    }

    protected function pruning(): void
    {
        Storage::disk(self::DISK)->delete(array_filter([$this->file_path, $this->error_report_path]));
    }

    /**
     * For withCount() / loadCount(): created_rows and updated_rows.
     *
     * @return array<string, Closure>
     */
    public static function outcomeCounts(): array
    {
        return [
            'rows as created_rows' => fn (Builder $q) => $q->where('outcome', ImportRowOutcome::Created),
            'rows as updated_rows' => fn (Builder $q) => $q->where('outcome', ImportRowOutcome::Updated),
        ];
    }

    /**
     * Share of rows processed so far, 0–100.
     */
    public function progress(): int
    {
        if ($this->status === ImportStatus::Completed) {
            return 100;
        }

        return $this->total_rows === 0 ? 0 : (int) min(99, floor($this->processed_rows / $this->total_rows * 100));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
