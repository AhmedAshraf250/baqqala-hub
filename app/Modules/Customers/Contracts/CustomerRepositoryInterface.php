<?php

namespace App\Modules\Customers\Contracts;

use App\Modules\Customers\Contracts\Data\CustomerCriteria;
use App\Modules\Customers\Contracts\Data\CustomerData;
use Illuminate\Support\Collection;

/**
 * The customers table, as data: fetch, search, save, delete.
 *
 * A repository in the ordinary sense — it stores and fetches, and knows no
 * business rule. Adding someone *to the shop's books*, with the rule on their
 * phone and the announcement that comes with it, is
 * {@see CustomerDirectoryInterface}. Other modules hold a customer id and
 * come here for the rest: there are no relations onto `Customer` from outside.
 *
 * A search reads the term the way the counter types it: a name in any of its
 * spellings — «احمد» finds «أحمد», «فاطمه» finds «فاطمة» — and the digits of a
 * phone, Arabic-Indic or Latin. Every word must match: «محمد 4567» is the
 * Mohamed whose phone has 4567 in it.
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
     * The customers a quick search finds, best first: a name that is the
     * term, then one that starts with it, then one with a word that does,
     * then the rest — and a phone that ends in the digits typed before one
     * that only has them. Empty for a term with nothing to search by.
     *
     * @return Collection<int, CustomerData>
     */
    public function search(string $term, int $limit = 10): Collection;

    /**
     * The ids of every customer the criteria keep, in one query and no
     * order, for a list that pages and sorts by what another module holds.
     *
     * @return list<int>
     */
    public function searchIds(CustomerCriteria $criteria): array;

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
