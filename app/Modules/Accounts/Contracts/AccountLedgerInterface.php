<?php

namespace App\Modules\Accounts\Contracts;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use App\Modules\Accounts\Contracts\Exceptions\CreditLimitExceeded;
use App\Modules\Accounts\Contracts\Exceptions\OverpaymentNotAllowed;

/**
 * The one write another module may make to the Accounts module: posting an
 * entry.
 *
 * Sales putting a basket on account asks the container for this, never for the
 * action behind it. It is not on the repository because it is not a read — it
 * locks a row and enforces the credit limit and the overpayment setting, and
 * those rules belong to one place. Reads go through
 * {@see AccountRepositoryInterface}.
 */
interface AccountLedgerInterface
{
    /**
     * Record an entry against the account of the given customer.
     *
     * The account is opened on first use if it does not exist yet, so a caller
     * never has to ask first.
     *
     * @param  int  $customerId  Whose account this is.
     * @param  int  $postedBy  The id of the administrator recording it.
     * @param  string|null  $reference  A caller's own reference, for tracing back.
     *
     * @throws CreditLimitExceeded when a debit would take the account past its limit
     * @throws OverpaymentNotAllowed when a credit would leave the shop owing the customer
     */
    public function post(
        int $customerId,
        TransactionType $type,
        Money $amount,
        int $postedBy,
        ?string $description = null,
        ?string $reference = null,
    ): void;
}
