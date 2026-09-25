<?php

namespace App\Modules\Customers\Contracts;

use App\Modules\Customers\Contracts\Data\CustomerData;
use Illuminate\Support\Collection;

/**
 * The customers table, as data: fetch, save, delete.
 *
 * A repository in the ordinary sense — it stores and fetches, and knows no
 * business rule. Adding someone *to the shop's books*, with the announcement
 * that comes with it, is `CreateCustomer`, which saves through here. Other
 * modules hold a customer id and come here for the rest: there are no
 * relations onto `Customer` from outside.
 */
interface CustomerRepositoryInterface
{
    /**
     * One customer, or null if there is no such record.
     */
    public function find(int $customerId): ?CustomerData;

    /**
     * Several at once, in one query, keyed by id. Ids with no record are
     * absent.
     *
     * @param  list<int>  $customerIds
     * @return Collection<int, CustomerData>
     */
    public function findMany(array $customerIds): Collection;

    /**
     * Store a customer — a new record when it has no id, the existing one
     * otherwise — and return it as stored.
     */
    public function save(CustomerData $customer): CustomerData;

    /**
     * Delete a customer.
     *
     * The database refuses while another module still holds rows that point
     * at them — an account with a ledger, for one — so a customer with history
     * cannot disappear from under it.
     */
    public function delete(int $customerId): void;
}
