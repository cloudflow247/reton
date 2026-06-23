<?php

declare(strict_types=1);

namespace App\Support\Money;

use Stringable;

/**
 * Immutable monetary value held in integer minor units (e.g. kobo).
 *
 * Floating point is never used to represent money anywhere in Reton. Every
 * amount that crosses the ledger is an integer count of the smallest
 * indivisible unit of its currency.
 */
final readonly class Money implements Stringable
{
    private function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public static function of(int $amount, string $currency): self
    {
        return new self($amount, self::normaliseCurrency($currency));
    }

    public static function zero(string $currency): self
    {
        return new self(0, self::normaliseCurrency($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->amount, $this->currency);
    }

    public function abs(): self
    {
        return new self(abs($this->amount), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->amount <=> $other->amount;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function __toString(): string
    {
        return $this->currency.' '.$this->amount;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw CurrencyMismatchException::between($this->currency, $other->currency);
        }
    }

    private static function normaliseCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidMoneyException("Invalid ISO-4217 currency code: '{$currency}'.");
        }

        return $currency;
    }
}
