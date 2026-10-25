<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Generated invoices are issued straight away (there is no draft state, ADR 0008). Payments
 * move them to partially paid and paid, refunds back again. Void is final: a voided invoice
 * is never paid and never generated again for that student and term. "Overdue" is not a
 * status but an issued or partially paid invoice past its due date.
 */
enum InvoiceStatus: string
{
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Issued => __('Issued'),
            self::PartiallyPaid => __('Partially paid'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    /**
     * The status of an invoice that is not void, from its total and what has been paid on
     * it (net of refunds), both in minor units.
     */
    public static function forAmounts(int $total, int $paid): self
    {
        return match (true) {
            $paid >= $total => self::Paid,
            $paid > 0 => self::PartiallyPaid,
            default => self::Issued,
        };
    }

    /**
     * Statuses with an amount still to pay.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Issued, self::PartiallyPaid];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }
}
