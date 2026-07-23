<?php

declare(strict_types=1);

use App\Support\Money\CurrencyMismatchException;
use App\Support\Money\InvalidMoneyException;
use App\Support\Money\Money;

it('constructs from minor units and exposes amount and currency', function () {
    $money = Money::of(150_00, 'NGN');

    expect($money->amount)->toBe(15000)
        ->and($money->currency)->toBe('NGN');
});

it('normalises currency to uppercase', function () {
    expect(Money::of(100, 'ngn')->currency)->toBe('NGN');
});

it('rejects malformed currency codes', function () {
    Money::of(100, 'NG');
})->throws(InvalidMoneyException::class);

it('creates a zero value', function () {
    $zero = Money::zero('NGN');

    expect($zero->amount)->toBe(0)
        ->and($zero->isZero())->toBeTrue();
});

it('adds two amounts of the same currency', function () {
    $sum = Money::of(100, 'NGN')->plus(Money::of(250, 'NGN'));

    expect($sum->amount)->toBe(350)
        ->and($sum->currency)->toBe('NGN');
});

it('subtracts two amounts of the same currency', function () {
    $diff = Money::of(500, 'NGN')->minus(Money::of(200, 'NGN'));

    expect($diff->amount)->toBe(300);
});

it('refuses to add across currencies', function () {
    Money::of(100, 'NGN')->plus(Money::of(100, 'USD'));
})->throws(CurrencyMismatchException::class);

it('refuses to subtract across currencies', function () {
    Money::of(100, 'NGN')->minus(Money::of(100, 'USD'));
})->throws(CurrencyMismatchException::class);

it('reports sign correctly', function () {
    expect(Money::of(1, 'NGN')->isPositive())->toBeTrue()
        ->and(Money::of(-1, 'NGN')->isNegative())->toBeTrue()
        ->and(Money::of(0, 'NGN')->isPositive())->toBeFalse();
});

it('negates and takes absolute value', function () {
    expect(Money::of(100, 'NGN')->negate()->amount)->toBe(-100)
        ->and(Money::of(-100, 'NGN')->abs()->amount)->toBe(100);
});

it('compares equality by amount and currency', function () {
    expect(Money::of(100, 'NGN')->equals(Money::of(100, 'NGN')))->toBeTrue()
        ->and(Money::of(100, 'NGN')->equals(Money::of(101, 'NGN')))->toBeFalse()
        ->and(Money::of(100, 'NGN')->equals(Money::of(100, 'USD')))->toBeFalse();
});

it('is immutable - operations return new instances', function () {
    $original = Money::of(100, 'NGN');
    $original->plus(Money::of(50, 'NGN'));

    expect($original->amount)->toBe(100);
});
