<?php

namespace App\Modules\Accounts\Database\Repositories;

use App\Modules\Accounts\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Contracts\Data\AccountStanding;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * Accounts, stored in and read from this module's own table.
 *
 * One instance per request, like every object this module hands out.
 */
#[Scoped]
final class AccountRepository implements AccountRepositoryInterface
{
    public function findByCustomer(int $customerId): ?AccountStanding
    {
        return CustomerAccount::query()->firstWhere('customer_id', $customerId)?->toStanding();
    }

    /**
     * @param  list<int>  $customerIds
     * @return Collection<int, AccountStanding>
     */
    public function findManyByCustomers(array $customerIds): Collection
    {
        if ($customerIds === []) {
            return collect();
        }

        return CustomerAccount::query()
            ->whereIn('customer_id', $customerIds)
            ->get()
            ->mapWithKeys(static fn (CustomerAccount $account): array => [
                $account->customer_id => $account->toStanding(),
            ]);
    }

    public function save(AccountStanding $account): AccountStanding
    {
        $record = CustomerAccount::query()->firstOrNew(['customer_id' => $account->customerId]);

        $record->credit_limit = $account->creditLimit;
        $record->save();

        return $record->toStanding();
    }
}
