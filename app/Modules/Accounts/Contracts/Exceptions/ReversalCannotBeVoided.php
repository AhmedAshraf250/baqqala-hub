<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use RuntimeException;

/**
 * Thrown when a reversal is voided. A reversal is the correction of another
 * entry; undoing it means entering that entry again, so the ledger never
 * holds a chain of cancellations to unpick.
 */
final class ReversalCannotBeVoided extends RuntimeException
{
    public function __construct(public readonly int $entryId)
    {
        parent::__construct("Entry [{$entryId}] is a reversal; enter the original again instead.");
    }
}
