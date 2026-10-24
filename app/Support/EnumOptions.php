<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;

/**
 * Turns an enum with a label() method into select options for the frontend.
 */
final class EnumOptions
{
    /**
     * @param  class-string<BackedEnum>  $enum
     * @return list<array{value: string, label: string}>
     */
    public static function of(string $enum): array
    {
        return array_map(
            fn (BackedEnum $case): array => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
            ],
            $enum::cases(),
        );
    }
}
