<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Modules\Accounts\Contracts\Enums\CorrectionReason;

/**
 * Cancel one entry: a reversal of the same amount the other way, pointing at
 * it. The entry itself stays in the ledger, marked by the reversal.
 */
final readonly class VoidRequest
{
    public function __construct(
        public int $entryId,
        public int $postedBy,
        public string $operationId,
        public CorrectionReason $reason,
        public ?string $note = null,
        public ?int $expectedLastEntryId = null,
    ) {}
}
