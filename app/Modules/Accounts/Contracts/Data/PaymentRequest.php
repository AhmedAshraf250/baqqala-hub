<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\OverpaymentChoice;

/**
 * Money handed over: «دفع».
 *
 * `expectedLastEntryId` is the latest entry the person saw when they decided
 * the amount. A payment is often «everything he owes»; if a colleague posted
 * since, the balance it was decided on is gone, and the payment is refused
 * with the new balance instead of settling the wrong sum. Null skips the
 * check, for a caller that decided nothing from a balance.
 */
final readonly class PaymentRequest
{
    public function __construct(
        public int $customerId,
        public int $postedBy,
        public string $operationId,
        public Money $amount,
        public ?string $note = null,
        public OverpaymentChoice $overpayment = OverpaymentChoice::Refuse,
        public ?int $expectedLastEntryId = null,
    ) {}
}
