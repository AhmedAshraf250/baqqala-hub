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
})->with(['', 'abc', '1.2.3', '1,250.00', '١٢٫٥٫٣']);

test('an amount typed on an Arabic keyboard reads as its Latin form', function (string $typed, string $read) {
    // Typed with Arabic-Indic digits and the Arabic decimal separator, the way
    // a cashier on an Arabic layout writes it — once refused outright.
    expect(Money::fromDecimalString($typed)->toDecimalString())->toBe($read);
})->with([
    'Arabic-Indic' => ['٣٥٠', '350.00'],
    'with the Arabic decimal separator' => ['١٢٫٥', '12.50'],
    'eastern forms' => ['۳۵۰٫۷۵', '350.75'],
    'mixed with Latin' => ['3٥0.5', '350.50'],
]);

test('a displayed amount groups its thousands', function (string $amount, string $shown) {
    expect(Money::fromDecimalString($amount)->toDisplayString())->toBe($shown);
})->with([
    ['0.00', '0.00'],
    ['999.99', '999.99'],
    ['1000.00', '1,000.00'],
    ['48350.00', '48,350.00'],
    ['1234567.89', '1,234,567.89'],
    ['-48350.50', '-48,350.50'],
]);

test('comparisons read the way the ledger needs them', function () {
    $ten = Money::fromDecimalString('10.00');
    $five = Money::fromDecimalString('5.00');

    expect($ten->isGreaterThan($five))->toBeTrue()
        ->and($five->isGreaterThan($ten))->toBeFalse()
        ->and($ten->minus($ten)->isZero())->toBeTrue()
        ->and($five->minus($ten)->isNegative())->toBeTrue()
        ->and($ten->equals(Money::fromMinorUnits(1000)))->toBeTrue();
});

test('formatting appends the currency as the reader\'s language writes it', function () {
    config()->set('foundation.currency.symbol', ['ar' => 'ج.م', 'en' => 'EGP']);

    app()->setLocale('ar');
    expect(Money::fromDecimalString('25.50')->format())->toBe('25.50 ج.م')
        ->and(Money::fromDecimalString('48350.00')->format())->toBe('48,350.00 ج.م');

    app()->setLocale('en');
    expect(Money::fromDecimalString('25.50')->format())->toBe('25.50 EGP');
});

test('a language with no symbol of its own shows the currency\'s code', function () {
    config()->set('foundation.currency.symbol', ['ar' => 'ج.م']);
    config()->set('foundation.currency.code', 'EGP');

    expect(currency_symbol('ar'))->toBe('ج.م')
        ->and(currency_symbol('fr'))->toBe('EGP');
});

test('a displayed amount is never what an input reads back', function () {
    // Inputs are filled with toDecimalString(); the grouped form is for
    // reading only, and parsing it is refused rather than misread.
    $amount = Money::fromDecimalString('1250.00');

    expect(Money::fromDecimalString($amount->toDecimalString())->equals($amount))->toBeTrue()
        ->and(fn () => Money::fromDecimalString($amount->toDisplayString()))->toThrow(InvalidArgumentException::class);
});
