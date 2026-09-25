<?php

namespace App\Foundation\Money;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact monetary amount, held as an integer number of minor units.
 *
 * Money is never stored or calculated as a float: PHP floats cannot represent
 * most decimal fractions exactly, SQLite stores DECIMAL columns as REAL, and
 * this deployment has no bcmath extension. Integer minor units sidestep all
 * three problems, so every balance is exact by construction.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(public int $minorUnits) {}

    /**
     * Build from a raw count of minor units (piastres, cents, ...).
     */
    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Zero, in the configured currency.
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Build from a human-entered decimal amount such as "125.50".
     *
     * Parsing is string-based so the value never passes through a float.
     */
    public static function fromDecimalString(string $amount): self
    {
        $amount = trim($amount);

        if (! preg_match('/^(?<sign>[+-]?)(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException("Cannot read [{$amount}] as a monetary amount.");
        }

        $precision = self::precision();
        $fraction = str_pad(substr($matches['fraction'] ?? '', 0, $precision), $precision, '0');
        $minorUnits = (int) ($matches['whole'].$fraction);

        return new self($matches['sign'] === '-' ? -$minorUnits : $minorUnits);
    }

    public function plus(self $other): self
    {
        return new self($this->minorUnits + $other->minorUnits);
    }

    public function minus(self $other): self
    {
        return new self($this->minorUnits - $other->minorUnits);
    }

    public function negated(): self
    {
        return new self(-$this->minorUnits);
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isNegative(): bool
    {
        return $this->minorUnits < 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->minorUnits > $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    /**
     * The amount as a plain decimal string, for example "125.50".
     */
    public function toDecimalString(): string
    {
        $precision = self::precision();
        $sign = $this->minorUnits < 0 ? '-' : '';
        $digits = str_pad((string) abs($this->minorUnits), $precision + 1, '0', STR_PAD_LEFT);

        if ($precision === 0) {
            return $sign.$digits;
        }

        return $sign.substr($digits, 0, -$precision).'.'.substr($digits, -$precision);
    }

    /**
     * The amount with its currency symbol, for display.
     */
    public function format(): string
    {
        return trim($this->toDecimalString().' '.config('foundation.currency.symbol'));
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimalString();
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    /**
     * How many decimal places the configured currency uses.
     *
     * Deliberately not cached: `config()` is a lookup in an already-loaded
     * array, and holding the value in a static would trade microseconds for a
     * piece of global state that outlives a request and surprises a test that
     * changes the currency.
     */
    private static function precision(): int
    {
        return (int) config('foundation.currency.precision', 2);
    }
}
