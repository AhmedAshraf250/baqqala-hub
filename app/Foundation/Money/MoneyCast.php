<?php

namespace App\Foundation\Money;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts an integer minor-unit column to and from a {@see Money} value object.
 *
 * @implements CastsAttributes<Money, mixed>
 */
class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromMinorUnits((int) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => $value->minorUnits,
            is_int($value) => $value,
            is_string($value) => Money::fromDecimalString($value)->minorUnits,
            default => throw new InvalidArgumentException(sprintf(
                'Attribute [%s] must be a Money instance, an integer of minor units, or a decimal string; %s given.',
                $key,
                get_debug_type($value),
            )),
        };
    }
}
