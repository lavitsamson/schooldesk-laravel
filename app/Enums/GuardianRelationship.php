<?php

declare(strict_types=1);

namespace App\Enums;

enum GuardianRelationship: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Grandparent = 'grandparent';
    case LegalGuardian = 'guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mother => __('Mother'),
            self::Father => __('Father'),
            self::Grandparent => __('Grandparent'),
            self::LegalGuardian => __('Legal guardian'),
            self::Other => __('Other'),
        };
    }
}
