<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value is the user id of an active teacher of the current school.
 */
final class SchoolTeacher implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isTeacher = is_numeric($value)
            && User::query()->teachers()->whereKey((int) $value)->exists();

        if (! $isTeacher) {
            $fail('Choose a teacher of this school.');
        }
    }
}
