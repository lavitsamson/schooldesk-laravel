<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How often a fee structure is billed on the term invoices of its academic year (ADR 0008):
 * - term: on every term's invoice;
 * - annual: once per student and year, on the first invoice that year;
 * - one_off: once per student ever, and only in the student's first year at the school
 *   (e.g. an admission fee).
 */
enum FeeFrequency: string
{
    case Term = 'term';
    case Annual = 'annual';
    case OneOff = 'one_off';

    public function label(): string
    {
        return match ($this) {
            self::Term => 'Every term',
            self::Annual => 'Once a year',
            self::OneOff => 'One-off (new students)',
        };
    }
}
