<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Policies\StudentPolicy;
use Carbon\CarbonImmutable;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $admission_no
 * @property string $first_name
 * @property string $last_name
 * @property CarbonImmutable $date_of_birth
 * @property Gender|null $gender
 * @property string|null $address
 * @property string|null $photo_path
 * @property StudentStatus $status
 * @property Carbon|null $anonymised_at
 * @property Carbon|null $deleted_at
 * @property-read Enrollment|null $currentEnrollment
 * @property-read GuardianStudent|null $pivot
 */
#[Fillable(['school_id', 'admission_no', 'first_name', 'last_name', 'date_of_birth', 'gender', 'address', 'photo_path', 'status'])]
#[UsePolicy(StudentPolicy::class)]
class Student extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Letters, digits and - _ . / (e.g. "2026-0042" or "7A/12").
     */
    public const ADMISSION_NO_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9\/._-]*$/';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'gender' => Gender::class,
            'status' => StudentStatus::class,
            'anonymised_at' => 'datetime',
        ];
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Case-insensitive match on first name, last name, "first last" or the admission number.
     * lower() + LIKE behaves the same on MySQL and PostgreSQL (where LIKE is case-sensitive).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($term))).'%';

        $query->where(fn (Builder $q) => $q
            ->whereRaw('lower(first_name) like ?', [$like])
            ->orWhereRaw('lower(last_name) like ?', [$like])
            ->orWhereRaw('lower(admission_no) like ?', [$like])
            ->orWhereRaw("lower(concat(first_name, ' ', last_name)) like ?", [$like]));
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The enrolment in the school's current academic year, if any.
     *
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->whereIn(
            'academic_year_id',
            AcademicYear::query()->current()->select('id'),
        );
    }

    /**
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /**
     * @return BelongsToMany<Guardian, $this, GuardianStudent>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->using(GuardianStudent::class)
            ->withPivot(['id', 'is_primary'])
            ->withTimestamps();
    }
}
