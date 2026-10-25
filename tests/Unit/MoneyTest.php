<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Support\Money;

it('parses amounts typed in English or German style into minor units', function (string $input, int $minor): void {
    expect(Money::parse($input, 'EUR')->minor)->toBe($minor);
})->with([
    ['1200', 120_000],
    ['1200.5', 120_050],
    ['1200,50', 120_050],
    ['1,200.50', 120_050],
    ['1.200,50', 120_050],
    // A dot or comma before exactly three digits groups thousands (as German Excel writes them).
    ['1.200', 120_000],
    ['12.345', 1_234_500],
    [' 0.07 ', 7],
    ['12 345,67', 1_234_567],
]);

it('rejects what is not an amount', function (string $input): void {
    Money::parse($input, 'EUR');
})->throws(InvalidArgumentException::class)->with(['', 'abc', '-5', '1.234.5', '1,234,5', '10.9999', '1e3', '9999999999999']);

it('has no minor units in zero-decimal currencies', function (): void {
    expect(Money::parse('1500', 'JPY')->minor)->toBe(1500)
        ->and(fn () => Money::parse('15.50', 'JPY'))->toThrow(InvalidArgumentException::class)
        ->and((new Money(1500, 'jpy'))->toDecimal())->toBe('1500');
});

it('adds and subtracts in integers, in one currency only', function (): void {
    $a = new Money(10, 'EUR');
    $b = new Money(20, 'EUR');

    // 0.1 + 0.2 is exactly 0.3 here, unlike with floats.
    expect($a->plus($b)->minor)->toBe(30)
        ->and($a->minus($b)->minor)->toBe(-10)
        ->and($a->minus($b)->isNegative())->toBeTrue()
        ->and(Money::sum([$a, $b, $b], 'EUR')->equals(new Money(50, 'EUR')))->toBeTrue()
        ->and($b->greaterThan($a))->toBeTrue()
        ->and(Money::zero('EUR')->isZero())->toBeTrue()
        ->and(fn () => $a->plus(new Money(10, 'USD')))->toThrow(InvalidArgumentException::class);
});

it('writes plain decimals and formats per locale', function (): void {
    expect((new Money(120_050, 'EUR'))->toDecimal())->toBe('1200.50')
        ->and((new Money(5, 'EUR'))->toDecimal())->toBe('0.05')
        ->and((new Money(-5, 'EUR'))->toDecimal())->toBe('-0.05')
        ->and((new Money(120_050, 'EUR'))->format('en'))->toBe('€1,200.50')
        ->and((new Money(120_050, 'EUR'))->format('de'))->toBe("1.200,50\u{00A0}€")
        ->and(json_encode(new Money(120_050, 'eur')))->toBe('{"amount":120050,"currency":"EUR"}');
});

it('rejects invalid currency codes', function (): void {
    new Money(1, 'EURO');
})->throws(InvalidArgumentException::class);

it('derives the invoice status from total and net payments', function (int $paid, InvoiceStatus $status): void {
    expect(InvoiceStatus::forAmounts(120_000, $paid))->toBe($status);
})->with([
    [0, InvoiceStatus::Issued],
    [1, InvoiceStatus::PartiallyPaid],
    [119_999, InvoiceStatus::PartiallyPaid],
    [120_000, InvoiceStatus::Paid],
    [150_000, InvoiceStatus::Paid],
]);
