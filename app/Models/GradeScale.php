<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One band of a school's grade scale, e.g. "B" for 80.00–89.99 %.
 */
#[Fillable(['school_id', 'label', 'min_percent', 'max_percent'])]
class GradeScale extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
        ];
    }
}
