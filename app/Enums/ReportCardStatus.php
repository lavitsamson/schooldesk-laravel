<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A report card is a draft while the teacher writes the comment (and its snapshot may be
 * recalculated), approved once the teacher signs it off (the PDF is rendered), and published
 * once parents can see it. Approved cards can be reopened; published ones are final.
 */
enum ReportCardStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Published => 'Published',
        };
    }
}
