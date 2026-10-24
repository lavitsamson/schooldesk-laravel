<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 */
#[Table('classes')]
#[Fillable(['school_id', 'academic_year_id', 'name', 'section', 'class_teacher_id'])]
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
}
