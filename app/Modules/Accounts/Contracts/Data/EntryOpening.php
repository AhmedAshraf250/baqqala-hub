<?php

namespace App\Modules\Accounts\Contracts\Data;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Contracts\Events\AccountEntryPosted;

/**
 * How another screen opens the drawer that records an entry — kept on every
 * admin screen for whoever may post — already filled in: whose account,
 * «أخذ» or «دفع», what it was and for how much, a note, and the operation id
 * the save will carry, so the opener knows its own entry when
 * {@see AccountEntryPosted} announces it.
 *
 * The person still checks it and saves it; nothing is posted by opening.
 * A screen dispatches it as a browser event:
 * `$this->dispatch(EntryOpening::EVENT, ...$opening->parameters())`.
 */
final readonly class EntryOpening
{
    public const string EVENT = 'open-entry';

    /**
     * @param  EntryKind  $kind  `Charge` or `Payment`; anything else opens a charge.
     * @param  string  $description  What a charge was — its one line's name — when there is an amount.
     * @param  string|null  $operationId  A UUID the opener keeps; a new one is made when null.
     */
    public function __construct(
        public int $customerId,
        public EntryKind $kind = EntryKind::Charge,
        public ?Money $amount = null,
        public string $description = '',
        public string $note = '',
        public ?string $operationId = null,
    ) {}

    /**
     * The event's parameters, by name.
     *
     * @return array{customerId: int, kind: string, amount: string|null, description: string, note: string, operationId: string|null}
     */
    public function parameters(): array
    {
        return [
            'customerId' => $this->customerId,
            'kind' => $this->kind === EntryKind::Payment ? 'payment' : 'charge',
            'amount' => $this->amount?->toDecimalString(),
            'description' => $this->description,
            'note' => $this->note,
            'operationId' => $this->operationId,
        ];
    }
}
