<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Enums\TransactionType;
use Carbon\CarbonImmutable;

/**
 * One entry as it stands in the ledger, with the balance on either side of it
 * — what a screen needs to say «owed 900, now 1,250».
 */
final readonly class PostedEntry
{
    public function __construct(
        public int $entryId,
        public int $customerId,
        public EntryKind $kind,
        public TransactionType $type,
        public Money $amount,
        public Money $outstandingBefore,
        public Money $outstandingAfter,
        public string $reference,
        public CarbonImmutable $postedAt,
    ) {}
}
