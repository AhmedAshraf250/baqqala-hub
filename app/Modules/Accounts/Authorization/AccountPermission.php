<?php

namespace App\Modules\Accounts\Authorization;

use App\Admin\Contracts\Authorization\PermissionInterface;
use Illuminate\Support\Str;

/**
 * What may be done with a customer's running account.
 *
 * Posting an entry is separated from reading one on purpose: everyone behind
 * the counter needs to see what a customer owes, but writing to a ledger is a
 * narrower trust.
 */
enum AccountPermission: string implements PermissionInterface
{
    case View = 'accounts.view';

    case Post = 'accounts.post';

    public function label(): string
    {
        return __('accounts::module.permissions.'.Str::snake($this->name));
    }

    public function group(): string
    {
        return 'accounts';
    }
}
