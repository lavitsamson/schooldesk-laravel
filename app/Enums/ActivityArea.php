<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The parts of the school the audit log covers; stored as the activity's log name.
 */
enum ActivityArea: string
{
    case Students = 'students';
    case Grades = 'grades';
    case Invoices = 'invoices';
    case Payments = 'payments';
    case ReportCards = 'report_cards';
    case Gdpr = 'gdpr';

    public function label(): string
    {
        return match ($this) {
            self::Students => __('Students'),
            self::Grades => __('Grades'),
            self::Invoices => __('Invoices'),
            self::Payments => __('Payments'),
            self::ReportCards => __('Report cards'),
            self::Gdpr => __('Data protection'),
        };
    }
}
