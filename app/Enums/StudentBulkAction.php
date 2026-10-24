<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Actions on several selected students at once.
 */
enum StudentBulkAction: string
{
    case MoveToClass = 'move_to_class';
    case MarkAsLeft = 'mark_as_left';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::MoveToClass => 'Move to class',
            self::MarkAsLeft => 'Mark as left',
            self::Export => 'Export selected (Excel)',
        };
    }
}
