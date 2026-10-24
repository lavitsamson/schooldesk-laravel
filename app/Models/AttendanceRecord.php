<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Policies\AttendancePolicy;
use Carbon\CarbonImmutable;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A student's attendance on one day (at most one record per student and day). class_id is
 * the class the attendance was taken in, so history stays put when a student moves class.
 *
 * @property int $id
 * @property int $student_id
 * @property int $class_id
 * @property CarbonImmutable $date
 * @property AttendanceStatus $status
 * @property string|null $note
 * @property int|null $recorded_by
 * @property Carbon|null $guardian_notified_at
 * @property Carbon|null $updated_at
 * @property-read Student $student
 * @property-read SchoolClass $schoolClass
 * @property-read User|null $recordedBy
 */
#[Fillable(['school_id', 'student_id', 'class_id', 'date', 'status', 'note', 'recorded_by'])]
#[UsePolicy(AttendancePolicy::class)]
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'status' => AttendanceStatus::class,
            'guardian_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
