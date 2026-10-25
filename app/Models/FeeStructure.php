<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeeFrequency;
use App\Models\Concerns\BelongsToSchool;
use App\Policies\FeeStructurePolicy;
use Database\Factories\FeeStructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fee charged on term invoices, for one academic year and one class or every class.
 * The amount is in minor units of the school's currency.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int|null $class_id
 * @property string $name
 * @property int $amount
 * @property FeeFrequency $frequency
 * @property int $due_day_offset
 * @property-read AcademicYear $academicYear
 * @property-read SchoolClass|null $schoolClass
 */
#[Fillable(['school_id', 'academic_year_id', 'class_id', 'name', 'amount', 'frequency', 'due_day_offset'])]
#[UsePolicy(FeeStructurePolicy::class)]
class FeeStructure extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FeeStructureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'frequency' => FeeFrequency::class,
            'due_day_offset' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
