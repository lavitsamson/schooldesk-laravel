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
            self::MoveToClass => __('Move to class'),
            self::MarkAsLeft => __('Mark as left'),
            self::Export => __('Export selected (Excel)'),
        };
    }
}
