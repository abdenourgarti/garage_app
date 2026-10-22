<?php

use App\Support\Decimal;

test('multiplication rounds to the nearest centime', function () {
    expect(Decimal::mul('2.5', '1500.00'))->toBe('3750.00')
        ->and(Decimal::mul('0.333', '10'))->toBe('3.33')
        ->and(Decimal::mul('0.335', '10'))->toBe('3.35')
        ->and(Decimal::mul('3', '0.125'))->toBe('0.38')
        ->and(Decimal::mul('-3', '0.125'))->toBe('-0.38');
});

test('division, sum, difference and comparison are exact', function () {
    expect(Decimal::div('6500', '5'))->toBe('1300.00')
        ->and(Decimal::div('1000', '3'))->toBe('333.33')
        ->and(Decimal::add('0.10', '0.20', '0.30'))->toBe('0.60')
        ->and(Decimal::sub('65000.00', '40000.50'))->toBe('24999.50')
        ->and(Decimal::cmp('10.00', '10'))->toBe(0)
        ->and(Decimal::cmp('9.99', '10'))->toBe(-1)
        ->and(Decimal::isZero('0.00'))->toBeTrue();
});

test('display trims useless zeros only after the decimal point', function () {
    expect(Decimal::display('10.00'))->toBe('10')
        ->and(Decimal::display('4.50'))->toBe('4,5')
        ->and(Decimal::display('100'))->toBe('100')
        ->and(Decimal::display('0.25'))->toBe('0,25');
});
