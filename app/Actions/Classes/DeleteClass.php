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

        // Teaching assignments go with it (cascade).
        $class->delete();
    }
}
