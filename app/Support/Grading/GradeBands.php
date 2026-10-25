<?php

declare(strict_types=1);

namespace App\Support\Grading;

use App\Models\GradeScale;

/**
 * A school's grade scale as bands from best to worst, e.g. A = 90.00–100.00 %. Bands are
 * inclusive on both ends and percentages have two decimals, so with contiguous bands (each
 * max 0.01 below the next min) every percentage falls into exactly one band.
 *
 * Comparisons are exact (Decimal), so 89.99 never ends up in the 90.00 band through float error.
 */
final class GradeBands
{
    /**
     * @param  array<int, array{label: string, min_percent: string, max_percent: string}>  $bands  best first
     */
    public function __construct(private readonly array $bands) {}

    /**
     * The current school's scale.
     */
    public static function current(): self
    {
        return new self(GradeScale::query()
            ->orderByDesc('min_percent')
            ->get(['label', 'min_percent', 'max_percent'])
            ->map(fn (GradeScale $band): array => [
                'label' => $band->label,
                'min_percent' => $band->min_percent,
                'max_percent' => $band->max_percent,
            ])
            ->values()
            ->all());
    }

    /**
     * The label of the band the percentage falls into, or null without a percentage (or
     * for a scale with a gap there).
     */
    public function labelFor(?string $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        foreach ($this->bands as $band) {
            if (Decimal::compare($percentage, $band['min_percent']) >= 0 && Decimal::compare($percentage, $band['max_percent']) <= 0) {
                return $band['label'];
            }
        }

        return null;
    }

    /**
     * @return list<array{label: string, min_percent: string, max_percent: string}>
     */
    public function all(): array
    {
        return array_values($this->bands);
    }

    /**
     * What is wrong with a scale, or [] if it covers 0–100 % without gaps or overlaps.
     *
     * @param  array<int, array{label: string, min_percent: string, max_percent: string}>  $bands  in any order
     * @return list<string>
     */
    public static function problems(array $bands): array
    {
        if ($bands === []) {
            return [__('Add at least one band.')];
        }

        usort($bands, fn (array $a, array $b): int => Decimal::compare($b['min_percent'], $a['min_percent']));
        $problems = [];

        foreach ($bands as $index => $band) {
            if (Decimal::compare($band['min_percent'], $band['max_percent']) > 0) {
                $problems[] = __(':grade: the lowest percentage is above the highest.', ['grade' => $band['label']]);
            }

            $higher = $bands[$index - 1] ?? null;
            if ($higher !== null && Decimal::compare(Decimal::add($band['max_percent'], '0.01'), $higher['min_percent']) !== 0) {
                $problems[] = __(':grade must end 0.01 below where :higher starts (:percent %).', ['grade' => $band['label'], 'higher' => $higher['label'], 'percent' => $higher['min_percent']]);
            }
        }

        if (Decimal::compare($bands[0]['max_percent'], '100') !== 0) {
            $problems[] = __('The best band must go up to 100 %.');
        }

        if (Decimal::compare($bands[count($bands) - 1]['min_percent'], '0') !== 0) {
            $problems[] = __('The lowest band must start at 0 %.');
        }

        return $problems;
    }
}
