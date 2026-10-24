<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case German = 'de';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'Deutsch',
        };
    }
}
