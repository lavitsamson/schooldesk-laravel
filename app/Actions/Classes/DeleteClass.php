<?php

declare(strict_types=1);

namespace App\Actions\Classes;

use App\Models\SchoolClass;
use Illuminate\Validation\ValidationException;

final class DeleteClass
{
    public function handle(SchoolClass $class): void
    {
        if ($class->enrollments()->exists()) {
            throw ValidationException::withMessages([
                'class' => 'Students are enrolled in this class. Move them to another class first.',
            ]);
        }

        if ($class->attendanceRecords()->exists()) {
            throw ValidationException::withMessages([
                'class' => 'Attendance has been taken in this class, so it is kept as history.',
            ]);
        }

        if ($class->assessments()->exists()) {
            throw ValidationException::withMessages([
                'class' => 'Assessments have been set in this class, so it is kept as history.',
            ]);
        }

        // Teaching assignments go with it (cascade).
        $class->delete();
    }
}
