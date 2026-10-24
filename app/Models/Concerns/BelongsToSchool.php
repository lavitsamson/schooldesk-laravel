<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\CrossSchoolWriteException;
use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every school-owned model:
 * - reads are filtered to the current school (and throw without one);
 * - school_id is filled from the current school on create;
 * - writing for another school, or moving a record between schools, throws.
 *
 * Seeders and factories may create records with an explicit school_id and no context.
 *
 * @property int $school_id
 * @property-read School $school
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            $current = app(CurrentSchool::class);

            if ($model->getAttribute('school_id') === null) {
                $model->setAttribute('school_id', $current->id());

                return;
            }

            if ($current->check() && (int) $model->getAttribute('school_id') !== $current->id()) {
                throw CrossSchoolWriteException::forModel($model::class);
            }
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('school_id')) {
                throw CrossSchoolWriteException::forModel($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
