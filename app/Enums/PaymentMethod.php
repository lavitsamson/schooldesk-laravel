<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stripe payments are recorded by the webhook only; cash and bank transfers by a school admin.
 */
enum PaymentMethod: string
{
    case Stripe = 'stripe';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Stripe => __('Card (Stripe)'),
            self::Cash => __('Cash'),
            self::BankTransfer => __('Bank transfer'),
        };
    }

    /**
     * Methods a school admin can record by hand.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Cash, self::BankTransfer];
    }
}
