<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Scopes\SchoolScope;
use App\Policies\StudentExportPolicy;
use Database\Factories\StudentExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A GDPR export of everything the school holds about a student: a ZIP built by a queued job,
 * whose download link is emailed to the admin who asked for it. The link and the file expire
 * after config('schooldesk.gdpr.export_link_hours'); model:prune then deletes both.
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $user_id
 * @property string|null $file_path
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property-read Student $student
 * @property-read User|null $user
 */
#[Fillable(['school_id', 'student_id', 'user_id', 'file_path', 'completed_at', 'expires_at'])]
#[UsePolicy(StudentExportPolicy::class)]
class StudentExport extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentExportFactory> */
    use HasFactory;

    use Prunable;

    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->file_path !== null && $this->expires_at?->isFuture() === true;
    }

    public function filePath(): string
    {
        return "schools/{$this->school_id}/exports/{$this->id}.zip";
    }

    /**
     * Expired exports, and ones whose job never finished. Runs for the whole platform, outside
     * any school context, hence no school scope.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->withoutGlobalScope(SchoolScope::class)
            ->where(fn (Builder $q) => $q
                ->where('expires_at', '<', now())
                ->orWhere(fn (Builder $q) => $q->whereNull('completed_at')->where('created_at', '<', now()->subDay())));
    }

    protected function pruning(): void
    {
        if ($this->file_path !== null) {
            Storage::disk(self::DISK)->delete($this->file_path);
        }
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
