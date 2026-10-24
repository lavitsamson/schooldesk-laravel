<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Policies\GradeScalePolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * One band of a school's grade scale, e.g. "B" for 80.00–89.99 %.
 *
 * @property int $id
 * @property string $label
 * @property string $min_percent
 * @property string $max_percent
 */
#[Fillable(['school_id', 'label', 'min_percent', 'max_percent'])]
#[UsePolicy(GradeScalePolicy::class)]
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
