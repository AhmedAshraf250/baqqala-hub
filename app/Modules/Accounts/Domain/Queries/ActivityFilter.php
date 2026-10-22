<?php

namespace App\Modules\Accounts\Domain\Queries;

/**
 * How an account has been used lately — worked out from its summary against
 * the thresholds in `accounts.dormant_after_days` and `accounts.late_after_days`
 * whenever the list asks, never stored.
 */
enum ActivityFilter: string
{
    /** An entry within the dormant threshold. */
    case Active = 'active';

    /** No entry for longer than the dormant threshold. */
    case Dormant = 'dormant';

    /** Owes, and has paid nothing for longer than the late threshold. */
    case Late = 'late';

    public function label(): string
    {
        return __('accounts::module.list.activity.'.$this->value);
    }
}
