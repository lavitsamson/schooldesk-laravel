<?php

declare(strict_types=1);

namespace App\Actions\Subjects;

use App\Models\Subject;
use Illuminate\Validation\ValidationException;

final class DeleteSubject
{
    public function handle(Subject $subject): void
    {
        if ($subject->assessments()->exists()) {
            throw ValidationException::withMessages([
                'subject' => 'Marks have been entered for this subject, so it is kept as history.',
            ]);
        }

        // Its teaching assignments go with it (cascade).
        $subject->delete();
    }
}
