<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;
use LogicException;

/**
 * What one save posted: one entry, or several that belong together — a
 * charge and the part paid at once, a reversal and its replacement.
 *
 * `replayed` says the operation had already been posted and nothing new was
 * written: the same save arrived twice, and this is what the first one did.
 */
final readonly class PostedOperation
{
    /**
     * In the order they were posted; never empty.
     *
     * @var non-empty-list<PostedEntry>
     */
    public array $entries;

    /**
     * @param  list<PostedEntry>  $entries
     */
    public function __construct(
        public string $operationId,
        array $entries,
        public bool $replayed = false,
    ) {
        if ($entries === []) {
            throw new LogicException('An operation posts at least one entry.');
        }

        $this->entries = $entries;
    }

    /**
     * The first entry: the charge, the payment, the reversal.
     */
    public function first(): PostedEntry
    {
        return $this->entries[0];
    }

    /**
     * The last entry, whose balance is where the account stands now.
     */
    public function last(): PostedEntry
    {
        return $this->entries[array_key_last($this->entries)];
    }

    /**
     * What is owed after the whole operation.
     */
    public function outstandingAfter(): Money
    {
        return $this->last()->outstandingAfter;
    }
}
