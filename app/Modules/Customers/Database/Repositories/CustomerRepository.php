<?php

namespace App\Modules\Customers\Database\Repositories;

use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerCriteria;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * @return Collection<int, CustomerData>
     */
    public function search(string $term, int $limit = 10): Collection
    {
        $words = Customer::searchWords($term);

        if ($words === []) {
            return collect();
        }

        $folded = implode(' ', $words);

        return Customer::query()
            ->matching($term)
            ->orderByRaw(
                'CASE WHEN search_name = ? THEN 0 WHEN search_name LIKE ? THEN 1 WHEN search_name LIKE ? THEN 2 WHEN phone LIKE ? THEN 3 ELSE 4 END',
                [$folded, "{$folded}%", "% {$folded}%", "%{$folded}"],
            )
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(static fn (Customer $customer): CustomerData => $customer->toData())
            ->values();
    }

    /**
     * @return list<int>
     */
    public function searchIds(CustomerCriteria $criteria): array
    {
        $ids = Customer::query()
            ->when($criteria->term !== null, static fn (Builder $query) => $query->matching((string) $criteria->term))
            ->when($criteria->groupIds !== [], static fn (Builder $query) => $query->whereIn(
                'id',
                DB::table('customer_group_members')->whereIn('group_id', $criteria->groupIds)->select('customer_id'),
            ))
            ->pluck('id');

        return array_values(array_map(intval(...), $ids->all()));
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
