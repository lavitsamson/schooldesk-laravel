<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Policies\ActivityPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * An entry of the school's audit log (spatie/laravel-activitylog). The models that use
 * LogsSchoolActivity write them; school_id and student_id come from the logged record.
 *
 * @property int|null $student_id
 * @property-read Student|null $student
 */
#[UsePolicy(ActivityPolicy::class)]
class Activity extends SpatieActivity
{
    use BelongsToSchool;

    /**
     * The student the entry is about, also once the student was deleted.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }
}
