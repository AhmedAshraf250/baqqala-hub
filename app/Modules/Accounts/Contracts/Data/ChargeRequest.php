<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;

/**
 * Goods taken on account: «أخذ».
 *
 * `operationId` is the id of the save that sent it, made once by the screen
 * when it opens the form. The same operation arriving twice — a double
 * click, a retry after the network dropped — is posted once. `paidNow` is
 * what was paid on the spot, posted as a payment in the same operation:
 * «took 350 and paid 100 now».
 */
final readonly class ChargeRequest
{
    /**
     * @param  list<ChargeLine>  $lines
     */
    public function __construct(
        public int $customerId,
        public int $postedBy,
        public string $operationId,
        public array $lines,
        public ?string $note = null,
        public ?Money $paidNow = null,
    ) {}
}
