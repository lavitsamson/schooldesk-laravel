<?php

declare(strict_types=1);

use App\Models\Activity;
use Spatie\Activitylog\Actions\CleanActivityLogAction;
use Spatie\Activitylog\Actions\LogActivityAction;

/*
 * spatie/laravel-activitylog: the school's audit trail. Entries belong to a school (school_id,
 * App\Models\Activity) and are written by the models that use LogsSchoolActivity.
 */
return [

    'enabled' => env('ACTIVITYLOG_ENABLED', true),

    /*
     * Used by `activitylog:clean`, which is not scheduled: the audit trail is kept. Schools
     * that need a shorter retention can run it.
     */
    'clean_after_days' => 365,

    'default_log_name' => 'default',

    'default_auth_driver' => null,

    /*
     * The audit log still names a student who was deleted (students are soft-deleted).
     */
    'include_soft_deleted_subjects' => true,

    'activity_model' => Activity::class,

    'default_except_attributes' => [],

    'buffer' => [
        'enabled' => false,
    ],

    'actions' => [
        'log_activity' => LogActivityAction::class,
        'clean_log' => CleanActivityLogAction::class,
    ],
];
