<?php

namespace App\Modules\Accounts\Domain\Actions;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Database\Models\CustomerAccount;

/**
 * Opens a customer's running account, or returns the one they already have.
 *
 * Idempotent, and safe under a race: two requests opening the same account at
 * once meet at the unique `customer_id` index, and the loser reads the
 * winner's row instead of failing.
 *
 * It runs when a customer is added, so an account is there from day one — and
 * again on the first posting, so an account the listener never got to open
 * (it failed, or the customer predates it) is opened then rather than turning
 * the posting into an error.
 */
final class OpenAccount
{
    public function handle(int $customerId): CustomerAccount
    {
        return CustomerAccount::query()->firstOrCreate(
            ['customer_id' => $customerId],
            ['credit_limit' => Money::fromDecimalString((string) config('accounts.default_credit_limit'))],
        );
    }
}
