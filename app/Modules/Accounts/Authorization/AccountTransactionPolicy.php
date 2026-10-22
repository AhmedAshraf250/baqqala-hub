<?php

namespace App\Modules\Accounts\Authorization;

use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use Carbon\CarbonImmutable;

/**
 * Who may void or correct an entry.
 *
 * Whoever recorded it, for a short while after — a mistake noticed while the
 * customer is still at the counter is the person's own to put right — and
 * anyone holding `accounts.correct`, at any time. The owner passes through
 * `Gate::before` like every check. A reversal is never voided, whoever asks;
 * that is the ledger's rule, not a permission.
 */
final class AccountTransactionPolicy
{
    public function correct(AdminUser $user, AccountTransaction $entry): bool
    {
        if (! $entry->kind->isCorrectable()) {
            return false;
        }

        if ($user->can(AccountPermission::Correct->value)) {
            return true;
        }

        return $user->getKey() === $entry->created_by
            && $user->can(AccountPermission::Post->value)
            && CarbonImmutable::instance($entry->created_at)->addMinutes(self::window())->isFuture();
    }

    /**
     * How long, in minutes, whoever recorded an entry may correct it alone.
     */
    public static function window(): int
    {
        return app(AccountSettings::class)->correctionWindowMinutes();
    }
}
