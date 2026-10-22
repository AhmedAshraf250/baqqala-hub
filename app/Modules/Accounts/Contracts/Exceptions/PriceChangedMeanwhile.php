<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use App\Foundation\Money\Money;
use RuntimeException;

/**
 * Thrown when a product's catalog price changed after the person saw it. The
 * charge is not posted at a price nobody looked at; the screen shows the new
 * one and asks again.
 */
final class PriceChangedMeanwhile extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly Money $seen,
        public readonly Money $current,
    ) {
        parent::__construct(sprintf(
            'The price of product [%d] changed from %s to %s.',
            $productId,
            $seen->toDecimalString(),
            $current->toDecimalString(),
        ));
    }
}
