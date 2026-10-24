<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\Term;

/**
 * The term a grades page shows: ?term=<id> if it is a term of the year, otherwise the
 * year's default term for today in the school's timezone (AcademicYear::defaultTerm()).
 * A term id of another year or school is a 404, like any other record not found.
 */
final class TermChoice
{
    public static function pick(AcademicYear $year, mixed $termId): Term
    {
        $year->loadMissing('terms');

        if ($termId !== null && $termId !== '') {
            return $year->terms->first(fn (Term $term): bool => (string) $term->id === (string) $termId) ?? abort(404);
        }

        return $year->defaultTerm(app(CurrentSchool::class)->get()->today()) ?? abort(404);
    }

    /**
     * The year's terms as select options, in order.
     *
     * @return array<int, array{id: int, name: string, starts_on: string, ends_on: string}>
     */
    public static function options(AcademicYear $year): array
    {
        return $year->terms
            ->sortBy('starts_on')
            ->map(fn (Term $term): array => [
                'id' => $term->id,
                'name' => $term->name,
                'starts_on' => $term->starts_on->toDateString(),
                'ends_on' => $term->ends_on->toDateString(),
            ])
            ->values()
            ->all();
    }
}
