<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's place in a class for one academic year (at most one per year).
 *
 * @property int $id
 * @property int $student_id
 * @property int $class_id
 * @property int $academic_year_id
 * @property int|null $roll_no
 * @property CarbonImmutable $enrolled_on
 * @property CarbonImmutable|null $left_on
 * @property-read Student $student
 * @property-read SchoolClass $schoolClass
 * @property-read AcademicYear $academicYear
 */
#[Fillable(['school_id', 'student_id', 'class_id', 'academic_year_id', 'roll_no', 'enrolled_on', 'left_on'])]
class Enrollment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrolled_on' => 'immutable_date',
            'left_on' => 'immutable_date',
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
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
