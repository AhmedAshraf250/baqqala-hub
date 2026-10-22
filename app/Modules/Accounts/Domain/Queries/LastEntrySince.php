<?php

namespace App\Modules\Accounts\Domain\Queries;

/**
 * How recent an account's latest entry must be for the list to keep it.
 * «Today» is the business day, which starts when the owner says it does.
 */
enum LastEntrySince: string
{
    case Today = 'today';

    case Week = 'week';

    case Month = 'month';

    public function label(): string
    {
        return __('accounts::module.list.since.'.$this->value);
    }
}
