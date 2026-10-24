<?php

declare(strict_types=1);

namespace App\Actions\Setup;

use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes the school's subjects match the given list (matched on code).
 */
final class SyncSubjects
{
    /**
     * @param  list<array{name: string, code: string}>  $subjects
     */
    public function handle(array $subjects): void
    {
        DB::transaction(function () use ($subjects): void {
            $keep = [];

            foreach ($subjects as $subject) {
                $keep[] = Subject::query()->updateOrCreate(
                    ['code' => Str::upper($subject['code'])],
                    ['name' => $subject['name']],
                )->id;
            }

            Subject::query()->whereNotIn('id', $keep)->delete();
        });
    }
}
