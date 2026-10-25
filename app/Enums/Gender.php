<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Optional. "Diverse" is the third legal option in Germany (divers).
 */
enum Gender: string
{
    case Female = 'female';
    case Male = 'male';
    case Diverse = 'diverse';

    public function label(): string
    {
        return match ($this) {
            self::Female => __('Female'),
            self::Male => __('Male'),
            self::Diverse => __('Diverse'),
        };
    }
}
