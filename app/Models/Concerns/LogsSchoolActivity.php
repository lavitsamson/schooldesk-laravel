<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ActivityArea;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Writes the model's changes to the school's audit log. An entry belongs to the school and
 * student of the logged record, not to the current school: a Stripe webhook or a seeder logs
 * for the record's school, and the GDPR erasure finds every entry about a student.
 *
 * Only the attributes in loggedAttributes() are kept, and an update that changes none of them
 * leaves no entry (e.g. an invoice's reminder count or a stored PDF path). Values are stored
 * serialised (enums as their value, dates as ISO 8601 in UTC); the audit log page formats them.
 *
 * @mixin Model
 */
trait LogsSchoolActivity
{
    use LogsActivity;

    abstract public function activityArea(): ActivityArea;

    /**
     * @return list<string>
     */
    abstract public function loggedAttributes(): array;

    abstract public function activityStudentId(): ?int;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityArea())
            ->logOnly($this->loggedAttributes())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Called by the activity logger for every entry about this record, also for ones logged by
     * hand with activity()->performedOn($record).
     */
    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->school_id = (int) $this->getAttribute('school_id');
        $activity->student_id = $this->activityStudentId();
    }
}
