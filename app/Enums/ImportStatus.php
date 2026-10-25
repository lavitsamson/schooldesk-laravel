<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Completed means every row was processed; some rows may still have failed validation.
 * Failed means the import itself stopped (an unreadable file or an unexpected error).
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting'),
            self::Processing => __('Importing'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
