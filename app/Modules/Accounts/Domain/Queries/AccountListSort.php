<?php

namespace App\Modules\Accounts\Domain\Queries;

/**
 * The orders the customer list offers. Not by name: the name is Customers',
 * and the list is sorted in the accounts query (decision 9 of the design).
 */
enum AccountListSort: string
{
    /** The latest entry first: whoever stands at the counter was likely served lately. */
    case Recent = 'recent';

    /** The largest balance owed first. */
    case Largest = 'largest';

    /** Owed the longest first. */
    case OldestDebt = 'oldest_debt';

    /** The newest customers first. */
    case Newest = 'newest';

    public function label(): string
    {
        return __('accounts::module.list.sort.'.$this->value);
    }
}
