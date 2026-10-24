<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * exists/unique rules limited to the current school. Laravel's rules query the table directly,
 * without Eloquent's global scopes, so a plain Rule::exists('classes', 'id') would accept
 * another school's class id.
 */
final class SchoolRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('school_id', app(CurrentSchool::class)->id());
    }

    public static function unique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('school_id', app(CurrentSchool::class)->id());
    }
}
