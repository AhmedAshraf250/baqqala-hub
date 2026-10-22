<?php

namespace App\Modules\Notebook\Contracts\Data;

use App\Foundation\Money\Money;
use App\Modules\Customers\Contracts\Data\CustomerData;

/**
 * What a note written in a hurry seems to say: who it may be about, the
 * likeliest first; how much; and what for, in the words that are left.
 *
 * A suggestion the person checks — never a decision. Nothing posts from it.
 */
final readonly class NoteReading
{
    /**
     * @param  list<CustomerData>  $customers
     */
    public function __construct(
        public array $customers = [],
        public ?Money $amount = null,
        public string $description = '',
    ) {}

    /**
     * The customer, when the note names exactly one.
     */
    public function customer(): ?CustomerData
    {
        return count($this->customers) === 1 ? $this->customers[0] : null;
    }
}
