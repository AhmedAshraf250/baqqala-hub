<?php

namespace App\Modules\Accounts\Domain\Queries;

/**
 * Where a balance stands, as the customer list filters by it.
 */
enum BalanceFilter: string
{
    /** «عليه»: owes the shop something. */
    case Owes = 'owes';

    /** «خالص»: owes nothing, and is owed nothing. */
    case Settled = 'settled';

    /** «له»: the shop holds money for them. */
    case InCredit = 'credit';

    /** Owes more than the limit set for them. */
    case OverLimit = 'over_limit';

    public function label(): string
    {
        return __('accounts::module.list.balance.'.$this->value);
    }
}
