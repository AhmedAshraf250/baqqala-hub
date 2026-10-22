<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use RuntimeException;

/**
 * Thrown when an entry that already has a reversal is voided or corrected
 * again. Each entry is cancelled once; the database refuses a second reversal
 * even when two arrive at the same moment.
 */
final class EntryAlreadyVoided extends RuntimeException
{
    public function __construct(
        public readonly int $entryId,
        public readonly int $reversedBy,
    ) {
        parent::__construct("Entry [{$entryId}] was already voided by entry [{$reversedBy}].");
    }
}
