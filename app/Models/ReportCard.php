<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Grades\CalculateReportCard;
use App\Enums\ActivityArea;
use App\Enums\CommentSource;
use App\Enums\ReportCardStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\LogsSchoolActivity;
use App\Policies\ReportCardPolicy;
use Database\Factories\ReportCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A student's report card for a term. `data` is the snapshot of CalculateReportCard, so a
 * card shows what was approved even after marks or the grade scale change (ADR 0007).
 *
 * @phpstan-import-type Snapshot from CalculateReportCard
 *
 * @property int $id
 * @property int $student_id
 * @property int $term_id
 * @property int $class_id
 * @property Snapshot $data
 * @property string|null $teacher_comment
 * @property CommentSource|null $comment_source
 * @property ReportCardStatus $status
 * @property string|null $pdf_path
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $published_at
 * @property Carbon $updated_at
 * @property-read Student $student
 * @property-read Term $term
 * @property-read SchoolClass $schoolClass
 * @property-read User|null $approver
 */
#[Fillable(['school_id', 'student_id', 'term_id', 'class_id', 'data', 'teacher_comment', 'comment_source', 'status', 'pdf_path', 'approved_by', 'approved_at', 'published_at'])]
#[UsePolicy(ReportCardPolicy::class)]
class ReportCard extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportCardFactory> */
    use HasFactory;

    use LogsSchoolActivity {
        getActivitylogOptions as schoolActivitylogOptions;
    }

    /**
     * Generating and recalculating drafts is routine; the audit log records the sign-offs.
     *
     * @var list<string>
     */
    protected static array $recordEvents = ['updated'];

    /**
     * Rendered PDFs live on the private disk and are only served through signed, authorised links.
     */
    public const DISK = 'local';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'comment_source' => CommentSource::class,
            'status' => ReportCardStatus::class,
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function activityArea(): ActivityArea
    {
        return ActivityArea::ReportCards;
    }

    /**
     * @return list<string>
     */
    public function loggedAttributes(): array
    {
        return ['status'];
    }

    public function activityStudentId(): int
    {
        return $this->student_id;
    }

    /**
     * Entries say what happened to the card: approved, published or reopened.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return $this->schoolActivitylogOptions()
            ->setDescriptionForEvent(fn (): string => match ($this->status) {
                ReportCardStatus::Draft => 'reopened',
                ReportCardStatus::Approved => 'approved',
                ReportCardStatus::Published => 'published',
            });
    }

    public function isDraft(): bool
    {
        return $this->status === ReportCardStatus::Draft;
    }

    public function pdfPath(): string
    {
        return "schools/{$this->school_id}/report-cards/{$this->term_id}/{$this->id}.pdf";
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<AiCommentDraft, $this>
     */
    public function aiDrafts(): HasMany
    {
        return $this->hasMany(AiCommentDraft::class);
    }
}
