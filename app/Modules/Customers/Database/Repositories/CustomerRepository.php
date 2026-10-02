<?php

namespace App\Modules\Customers\Database\Repositories;

use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * Customers, stored in and read from this module's own table.
 *
 * One instance per request: every caller in the request shares it.
 */
#[Scoped]
final class CustomerRepository implements CustomerRepositoryInterface
{
    public function find(int $customerId): ?CustomerData
    {
        return Customer::query()->find($customerId)?->toData();
    }

    /**
     * @param  list<int>  $customerIds
     * @return Collection<int, CustomerData>
     */
    public function findMany(array $customerIds): Collection
    {
        if ($customerIds === []) {
            return collect();
        }

        return Customer::query()
            ->whereKey($customerIds)
            ->get()
            ->mapWithKeys(static fn (Customer $customer): array => [
                $customer->getKey() => $customer->toData(),
            ]);
    }

    public function save(CustomerData $customer): CustomerData
    {
        $record = $customer->id === null
            ? new Customer
            : Customer::query()->findOrFail($customer->id);

        // Never `user_id`: which login opens a customer's portal is a rule —
        // a frontend login, linked once, under a lock — kept by CreateCustomer
        // and GrantPortalAccess. Through here, any module holding a DTO could
        // link an administrator's login or unlink a customer's.
        $record->fill([
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'notes' => $customer->notes,
        ])->save();

        return $record->toData();
    }

    public function delete(int $customerId): void
    {
        Customer::query()->findOrFail($customerId)->delete();
    }
}
