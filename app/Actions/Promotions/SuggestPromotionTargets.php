<?php

declare(strict_types=1);

namespace App\Actions\Promotions;

/**
 * Suggests where each class goes next year: "Grade 7 A" → "Grade 8 A" (or another "Grade 8"
 * section if there is no A). The highest grade graduates. Classes without a number in their
 * name get no suggestion. The admin reviews every suggestion before promoting.
 */
final class SuggestPromotionTargets
{
    public const GRADUATE = 'graduate';

    /**
     * @param  array<int, array{id: int, name: string, section: string|null}>  $from
     * @param  array<int, array{id: int, name: string, section: string|null}>  $to
     * @return array<int, int|self::GRADUATE|null> target per source class id
     */
    public function handle(array $from, array $to): array
    {
        $grades = array_filter(array_map(fn (array $class): ?int => $this->parse($class['name'])[1], $from), 'is_int');
        $highest = $grades === [] ? null : max($grades);

        $suggestions = [];

        foreach ($from as $class) {
            [$prefix, $grade] = $this->parse($class['name']);

            if ($grade === null) {
                $suggestions[$class['id']] = null;

                continue;
            }

            $candidates = array_values(array_filter(
                $to,
                fn (array $target): bool => $this->parse($target['name']) === [$prefix, $grade + 1],
            ));
            $sameSection = array_values(array_filter($candidates, fn (array $target): bool => $target['section'] === $class['section']));

            $match = $sameSection[0] ?? $candidates[0] ?? null;

            $suggestions[$class['id']] = match (true) {
                $match !== null => $match['id'],
                $grade === $highest => self::GRADUATE,
                default => null,
            };
        }

        return $suggestions;
    }

    /**
     * "Grade 7" → ["grade", 7], "Klasse 10" → ["klasse", 10], "Kindergarten" → ["kindergarten", null].
     *
     * @return array{0: string, 1: int|null}
     */
    private function parse(string $name): array
    {
        if (preg_match('/^(.*?)\s*(\d+)\s*$/u', $name, $matches) === 1) {
            return [mb_strtolower($matches[1]), (int) $matches[2]];
        }

        return [mb_strtolower(trim($name)), null];
    }
}
