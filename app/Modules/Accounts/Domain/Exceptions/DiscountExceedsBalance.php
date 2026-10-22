<?php

namespace App\Modules\Accounts\Domain\Exceptions;

use App\Foundation\Money\Money;
use RuntimeException;

/**
 * Thrown when a discount is larger than what is owed. Forgiving part of a
 * debt lowers it; it never leaves the shop owing the customer.
 */
final class DiscountExceedsBalance extends RuntimeException
{
    public function __construct(
        public readonly int $customerId,
        public readonly Money $discount,
        public readonly Money $outstanding,
    ) {
        parent::__construct(sprintf(
            'A discount of %s is more than the %s owed.',
            $discount->toDecimalString(),
            $outstanding->toDecimalString(),
        ));
    }
}
