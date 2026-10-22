<?php

namespace App\Modules\Customers\Contracts\Exceptions;

use RuntimeException;

/**
 * Thrown when a customer is given a phone number another customer already
 * has.
 *
 * One number is one person: a second record under it would split their
 * ledger in two. Carries who holds the number, when that is known, so the
 * screen can offer to open them instead.
 */
final class PhoneAlreadyUsed extends RuntimeException
{
    public function __construct(
        public readonly string $phone,
        public readonly ?int $heldByCustomerId,
    ) {
        parent::__construct($heldByCustomerId === null
            ? "The phone number [{$phone}] is already another customer's."
            : "The phone number [{$phone}] is already customer [{$heldByCustomerId}]'s.");
    }
}
