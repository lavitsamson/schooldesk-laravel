<?php

declare(strict_types=1);

namespace App\Enums;

enum GradeScalePreset: string
{
    case LetterAToF = 'a_f';
    case German1To6 = 'german_1_6';

    public function label(): string
    {
        return match ($this) {
            self::LetterAToF => 'A–F (letter grades)',
            self::German1To6 => 'German 1–6 (1 = sehr gut, 6 = ungenügend)',
        };
    }

    /**
     * Bands from best to worst. Percentages are inclusive on both ends; each band's max is
     * 0.01 below the next band's min so a percentage rounded to two decimals hits one band.
     *
     * @return list<array{label: string, min_percent: string, max_percent: string}>
     */
    public function bands(): array
    {
        $bands = match ($this) {
            self::LetterAToF => [
                ['A', '90.00', '100.00'],
                ['B', '80.00', '89.99'],
                ['C', '70.00', '79.99'],
                ['D', '60.00', '69.99'],
                ['E', '50.00', '59.99'],
                ['F', '0.00', '49.99'],
            ],
            self::German1To6 => [
                ['1', '92.00', '100.00'],
                ['2', '81.00', '91.99'],
                ['3', '67.00', '80.99'],
                ['4', '50.00', '66.99'],
                ['5', '30.00', '49.99'],
                ['6', '0.00', '29.99'],
            ],
        };

        return array_map(
            fn (array $band): array => ['label' => $band[0], 'min_percent' => $band[1], 'max_percent' => $band[2]],
            $bands,
        );
    }
}
