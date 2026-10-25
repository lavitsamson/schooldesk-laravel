<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an import batch creates. Students for now; the batch table is shared by later imports.
 */
enum ImportType: string
{
    case Students = 'students';

    public function label(): string
    {
        return match ($this) {
            self::Students => __('Students'),
        };
    }
}
