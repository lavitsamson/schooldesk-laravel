<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;

/**
 * Dates in the active language for messages, mails, PDFs and exports: "26 October 2026",
 * "26. Oktober 2026", "26 अक्टूबर 2026". Formats come from Carbon's locale data (isoFormat),
 * so each language gets its own day-month order.
 */
final class LocalDate
{
    /** "26 October 2026" */
    public static function long(CarbonInterface $date): string
    {
        return self::localised($date)->isoFormat('LL');
    }

    /** "Monday, 26 October 2026" */
    public static function withWeekday(CarbonInterface $date): string
    {
        return self::localised($date)->isoFormat('dddd, LL');
    }

    /** "26 Oct 2026" */
    public static function short(CarbonInterface $date): string
    {
        return self::localised($date)->isoFormat('ll');
    }

    /** "October 2026" */
    public static function monthAndYear(CarbonInterface $date): string
    {
        return self::localised($date)->isoFormat('MMMM YYYY');
    }

    /** "Mon 26" (column headings of the attendance export) */
    public static function weekdayAndDay(CarbonInterface $date): string
    {
        return self::localised($date)->isoFormat('ddd D');
    }

    private static function localised(CarbonInterface $date): CarbonInterface
    {
        return $date->copy()->locale(self::carbonLocale());
    }

    /** English dates are British ("20 July 2026"), as on the frontend: the schools are in Europe. */
    private static function carbonLocale(): string
    {
        $locale = App::getLocale();

        return $locale === 'en' ? 'en_GB' : $locale;
    }
}
