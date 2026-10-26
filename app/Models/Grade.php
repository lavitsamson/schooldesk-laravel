<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityArea;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\LogsSchoolActivity;
use App\Policies\GradePolicy;
use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's marks in one assessment (at most one per student and assessment). Marks are
 * null when there is only a remark, e.g. the student was absent.
 *
 * @property int $id
 * @property int $assessment_id
 * @property int $student_id
 * @property string|null $marks
 * @property string|null $remarks
 * @property int|null $entered_by
 * @property Carbon|null $updated_at
 * @property-read Assessment $assessment
 * @property-read Student $student
 * @property-read User|null $enteredBy
 */
#[Fillable(['school_id', 'assessment_id', 'student_id', 'marks', 'remarks', 'entered_by'])]
#[UsePolicy(GradePolicy::class)]
class Grade extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    use LogsSchoolActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
        ];
    }

    public function activityArea(): ActivityArea
    {
        return ActivityArea::Grades;
    }

    /**
     * @return list<string>
     */
    public function loggedAttributes(): array
    {
        return ['marks', 'remarks'];
    }

    public function activityStudentId(): ?int
    {
        return $this->student_id;
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
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
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
