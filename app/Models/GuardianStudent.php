<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Links a guardian to a student. No school_id of its own: both ends are school-owned, and
 * LinkGuardian only links records of the same school.
 *
 * @property int $id
 * @property int $guardian_id
 * @property int $student_id
 * @property bool $is_primary
 */
#[Table('guardian_student', incrementing: true)]
class GuardianStudent extends Pivot
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
