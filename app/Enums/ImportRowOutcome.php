<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportRowOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Failed => 'Failed',
        };
    }
}
