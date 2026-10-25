<?php

declare(strict_types=1);

namespace App\Support\Grading;

/**
 * How a student's overall percentage moved since the previous term, in words an AI comment
 * draft can use without seeing either number: improving, steady or declining. Moves smaller
 * than THRESHOLD percentage points count as steady, so noise does not read as a trend.
 */
final class ResultTrend
{
    public const IMPROVING = 'improving';

    public const STEADY = 'steady';

    public const DECLINING = 'declining';

    public const THRESHOLD = '3.00';

    /**
     * @return self::IMPROVING|self::STEADY|self::DECLINING|null null without both results
     */
    public static function between(?string $previous, ?string $current): ?string
    {
        if ($previous === null || $current === null) {
            return null;
        }

        $change = Decimal::sub($current, $previous);

        return match (true) {
            Decimal::compare($change, self::THRESHOLD) >= 0 => self::IMPROVING,
            Decimal::compare($change, '-'.self::THRESHOLD) <= 0 => self::DECLINING,
            default => self::STEADY,
        };
    }
}
