<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Policies\SchoolClassPolicy;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class (e.g. "Grade 7", section "A") in one academic year. Named SchoolClass because
 * "Class" is a reserved word in PHP.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property string $name
 * @property string|null $section
 * @property int|null $class_teacher_id
 * @property-read AcademicYear $academicYear
 * @property-read User|null $classTeacher
 * @property-read int|null $enrollments_count
 * @property-read int|null $teaching_assignments_count
 * @property-read int|null $students_count
 * @property-read int|null $recorded_count
 */
#[Table('classes')]
#[Fillable(['school_id', 'academic_year_id', 'name', 'section', 'class_teacher_id'])]
#[UsePolicy(SchoolClassPolicy::class)]
class SchoolClass extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    public function displayName(): string
    {
        return $this->section === null ? $this->name : "{$this->name} {$this->section}";
    }

    /**
     * A teacher's own classes: those they are class teacher of or teach a subject in.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function taughtBy(Builder $query, User $teacher): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('class_teacher_id', $teacher->id)
            ->orWhereHas('teachingAssignments', fn (Builder $assignments) => $assignments->where('teacher_id', $teacher->id)));
    }

    public function isTaughtBy(User $teacher): bool
    {
        return $this->class_teacher_id === $teacher->id
            || $this->teachingAssignments()->where('teacher_id', $teacher->id)->exists();
    }

    /**
     * Whether the teacher is assigned to teach the subject in this class.
     */
    public function teachesSubject(User $teacher, Subject $subject): bool
    {
        return $this->teachingAssignments()
            ->where('subject_id', $subject->id)
            ->where('teacher_id', $teacher->id)
            ->exists();
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /**
     * @return HasMany<TeachingAssignment, $this>
     */
    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'class_id');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'class_id');
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'class_id');
    }
}
