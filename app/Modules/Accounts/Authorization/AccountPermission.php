<?php

namespace App\Modules\Accounts\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with a customer's running account.
 *
 * Posting an entry is separated from reading one on purpose: everyone behind
 * the counter needs to see what a customer owes, but writing to a ledger is a
 * narrower trust. Narrower still: putting right someone else's entry, or an
 * old one (`Correct`), and the entries that are a decision rather than a
 * sale — an opening balance, a discount, a credit limit (`Adjust`). Whoever
 * posts may still correct their own entry for a short while; see
 * AccountTransactionPolicy.
 */
enum AccountPermission: string implements PermissionInterface
{
    case View = 'accounts.view';

    case Post = 'accounts.post';

    case Correct = 'accounts.correct';

    case Adjust = 'accounts.adjust';

    public function label(): string
    {
        return __('accounts::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'accounts';
    }
}
