<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case German = 'de';
    case Hindi = 'hi';

    /** The language's own name, as shown in language pickers. */
    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'Deutsch',
            self::Hindi => 'हिन्दी',
        };
    }

    /** The language's English name (for prompts to the LLM). */
    public function englishName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'German',
            self::Hindi => 'Hindi',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $locale): string => $locale->value, self::cases());
    }
}
