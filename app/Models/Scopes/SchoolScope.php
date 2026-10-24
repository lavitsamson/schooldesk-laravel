<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\CurrentSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class SchoolScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Throws when no school is set: an unscoped query must never return every school's rows.
        $builder->where($model->qualifyColumn('school_id'), app(CurrentSchool::class)->id());
    }
}
