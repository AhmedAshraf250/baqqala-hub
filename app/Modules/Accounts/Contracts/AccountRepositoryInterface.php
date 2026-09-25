<?php

namespace App\Modules\Accounts\Contracts;

use App\Modules\Accounts\Contracts\Data\AccountStanding;
use Illuminate\Support\Collection;

/**
 * The accounts table, as data: fetch and save.
 *
 * A repository in the ordinary sense — it stores and fetches, and knows no
 * business rule. An account that does not exist is absent; what that means is
 * the caller's decision.
 *
 * Two operations a table usually has are deliberately missing:
 *
 * - **Writing what the customer owes.** `outstanding` is the ledger's total,
 *   changed only by posting an entry through {@see AccountLedgerInterface::post()},
 *   which locks the row and enforces the credit limit. A `save()` that wrote it
 *   would reopen the hole the ledger exists to close, so `save()` stores the
 *   account's settings and never its total.
 * - **Delete.** An account with a history is never deleted; a mistake is
 *   corrected by posting its inverse.
 */
interface AccountRepositoryInterface
{
    /**
     * The account of the given customer, or null if they have none.
     */
    public function findByCustomer(int $customerId): ?AccountStanding;

    /**
     * The accounts of several customers, in one query, keyed by customer id.
     * Customers with no account are absent.
     *
     * @param  list<int>  $customerIds
     * @return Collection<int, AccountStanding>
     */
    public function findManyByCustomers(array $customerIds): Collection;

    /**
     * Store a customer's account settings — its credit limit — opening the
     * account if there is none. `outstanding` in what is passed is ignored;
     * the returned value has the real one.
     */
    public function save(AccountStanding $account): AccountStanding;
}
