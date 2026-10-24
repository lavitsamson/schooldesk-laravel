<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Policies\AcademicYearPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property bool $is_current
 * @property-read int|null $classes_count
 * @property-read int|null $enrollments_count
 */
#[Fillable(['school_id', 'name', 'starts_on', 'ends_on', 'is_current'])]
#[UsePolicy(AcademicYearPolicy::class)]
class AcademicYear extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * The school's current academic year (exactly one once the school is set up).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }

    /**
     * The term to show by default on the date: the one it falls in, else the last one that
     * has started (holidays between terms), else the first (before the year begins).
     */
    public function defaultTerm(CarbonImmutable $date): ?Term
    {
        $terms = $this->terms->sortBy('starts_on')->values();

        return $terms->first(fn (Term $term): bool => $date->between($term->starts_on, $term->ends_on))
            ?? $terms->last(fn (Term $term): bool => $term->starts_on->lte($date))
            ?? $terms->first();
    }

    /**
     * @return HasMany<Term, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
