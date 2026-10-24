<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentType;
use App\Models\Concerns\BelongsToSchool;
use App\Policies\AssessmentPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A test, exam or assignment of one subject in one class and term. Marks are out of
 * max_marks; weight says how much it counts towards the subject's term result.
 *
 * @property int $id
 * @property int $class_id
 * @property int $subject_id
 * @property int $term_id
 * @property string $name
 * @property AssessmentType $type
 * @property int $max_marks
 * @property string $weight
 * @property CarbonImmutable $held_on
 * @property int|null $created_by
 * @property-read SchoolClass $schoolClass
 * @property-read Subject $subject
 * @property-read Term $term
 * @property-read int|null $grades_count
 * @property-read int|null $graded_count
 */
#[Fillable(['school_id', 'class_id', 'subject_id', 'term_id', 'name', 'type', 'max_marks', 'weight', 'held_on', 'created_by'])]
#[UsePolicy(AssessmentPolicy::class)]
class Assessment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssessmentType::class,
            'max_marks' => 'integer',
            'weight' => 'decimal:2',
            'held_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
