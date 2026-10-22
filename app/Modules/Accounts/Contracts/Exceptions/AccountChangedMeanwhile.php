<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use App\Foundation\Money\Money;
use RuntimeException;

/**
 * Thrown when an entry was decided against a balance that is gone: someone
 * posted to the account after the person looked at it.
 *
 * Carries where the account stands now, so the screen can show it and ask
 * again instead of settling the wrong sum.
 */
final class AccountChangedMeanwhile extends RuntimeException
{
    public function __construct(
        public readonly int $customerId,
        public readonly ?int $expectedLastEntryId,
        public readonly ?int $lastEntryId,
        public readonly Money $outstanding,
    ) {
        parent::__construct(sprintf(
            'The account changed since it was read: its latest entry is [%s], not [%s]. It now stands at %s.',
            $lastEntryId ?? 'none',
            $expectedLastEntryId ?? 'none',
            $outstanding->toDecimalString(),
        ));
    }
}
