<?php

declare(strict_types=1);

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Left = 'left';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Left => __('Left'),
            self::Graduated => __('Graduated'),
        };
    }
}
