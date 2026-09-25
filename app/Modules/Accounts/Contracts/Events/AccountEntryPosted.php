<?php

namespace App\Modules\Accounts\Contracts\Events;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An entry was committed to a customer's ledger.
 *
 * Held until the outermost database transaction commits, so a listener only
 * ever hears about a settled fact. That matters the moment another module
 * posts from inside its own transaction — a sale and its ledger entry saved
 * together — and then rolls back: the entry never happened, and nobody is
 * told it did.
 */
final class AccountEntryPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $customerId,
        public readonly TransactionType $type,
        public readonly Money $amount,
        public readonly Money $outstandingAfter,
        public readonly string $reference,
    ) {}
}
