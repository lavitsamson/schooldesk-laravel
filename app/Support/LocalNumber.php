<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;
use NumberFormatter;

/**
 * Numbers in the active language for PDFs and exports: "86.5" in English, "86,5" in German.
 * Only formats; grade maths stays in App\Support\Grading\Decimal.
 */
final class LocalNumber
{
    /** "86.5 %", or a dash for no value. */
    public static function percent(float|int|string|null $value, int $maxDecimals = 1): string
    {
        return $value === null ? '—' : self::decimal($value, $maxDecimals).' %';
    }

    /** "89.99" in English, "89,99" in German; no trailing zeros. */
    public static function decimal(float|int|string $value, int $maxDecimals = 2): string
    {
        $formatter = new NumberFormatter(App::getLocale(), NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $maxDecimals);

        return (string) $formatter->format((float) $value);
    }
}
