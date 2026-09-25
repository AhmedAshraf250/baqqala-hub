<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use App\Foundation\Money\Money;
use RuntimeException;

/**
 * Thrown when a payment would leave the shop owing the customer.
 *
 * A customer paying more than their tab puts `outstanding` below zero, which
 * means the shop holds their money. Some shops want that — a deposit against
 * future purchases — and some consider it a data-entry mistake, so it is a
 * configured decision (`accounts.allow_overpayment`) rather than a hard rule.
 */
final class OverpaymentNotAllowed extends RuntimeException
{
    public function __construct(
        public readonly int $customerId,
        public readonly Money $attemptedOutstanding,
    ) {
        parent::__construct(sprintf(
            'This payment would take the account to %s, leaving the shop in debt to the customer.',
            $attemptedOutstanding->toDecimalString(),
        ));
    }
}
