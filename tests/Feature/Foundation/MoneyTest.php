<?php

use App\Foundation\Money\Money;

test('a decimal string round-trips exactly', function (string $amount) {
    expect(Money::fromDecimalString($amount)->toDecimalString())->toBe($amount);
})->with(['0.00', '0.01', '25.50', '999999.99', '-12.34']);

test('amounts that a float would corrupt stay exact', function () {
    $total = Money::fromDecimalString('0.10')
        ->plus(Money::fromDecimalString('0.20'));

    expect($total->toDecimalString())->toBe('0.30')
        ->and($total->minorUnits)->toBe(30);
});

test('adding a long run of small amounts does not drift', function () {
    $total = Money::zero();

    for ($i = 0; $i < 1000; $i++) {
        $total = $total->plus(Money::fromDecimalString('0.07'));
    }

    expect($total->toDecimalString())->toBe('70.00');
});

test('a fraction longer than the currency precision is truncated, not rounded through a float', function () {
    expect(Money::fromDecimalString('1.999')->toDecimalString())->toBe('1.99');
});

test('an unreadable amount is rejected', function (string $amount) {
    expect(fn () => Money::fromDecimalString($amount))->toThrow(InvalidArgumentException::class);
})->with(['', 'abc', '1.2.3', '١٢٫٥']);

test('comparisons read the way the ledger needs them', function () {
    $ten = Money::fromDecimalString('10.00');
    $five = Money::fromDecimalString('5.00');

    expect($ten->isGreaterThan($five))->toBeTrue()
        ->and($five->isGreaterThan($ten))->toBeFalse()
        ->and($ten->minus($ten)->isZero())->toBeTrue()
        ->and($five->minus($ten)->isNegative())->toBeTrue()
        ->and($ten->equals(Money::fromMinorUnits(1000)))->toBeTrue();
});

test('formatting appends the configured currency symbol', function () {
    config()->set('foundation.currency.symbol', 'ج.م');

    expect(Money::fromDecimalString('25.50')->format())->toBe('25.50 ج.م');
});
