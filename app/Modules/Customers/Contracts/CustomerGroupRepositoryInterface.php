<?php

namespace App\Modules\Customers\Contracts;

use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use Illuminate\Support\Collection;

/**
 * The owner's customer groups, and who is in each, as data.
 *
 * A customer may be in any number of groups. Archiving a group hides it —
 * from the chips, the choices, and a customer's groups — and keeps who is in
 * it for the day it is restored; only a group nobody is in may be deleted.
 */
interface CustomerGroupRepositoryInterface
{
    /**
     * Every group with how many customers are in it, keyed by id, in the
     * owner's order and then by name. Archived ones only when asked for.
     *
     * @return Collection<int, CustomerGroupData>
     */
    public function getAll(bool $includeArchived = false): Collection;

    /**
     * One group, archived or not, with its member count, or null if there is
     * no such group.
     */
    public function find(int $groupId): ?CustomerGroupData;

    /**
     * Store a group — a new one when it has no id, the existing one
     * otherwise: renamed, recoloured, reordered, archived, or restored — and
     * return it as stored. One in the trash, saved with no `deletedAt`, is
     * brought back.
     */
    public function save(CustomerGroupData $group): CustomerGroupData;

    /**
     * Delete a group into the trash, where it is kept, out of every list,
     * until it is brought back or deleted for good. Only an empty group is
     * deleted — a group with members is archived, so nobody's membership is
     * lost by a click — and the screen that deletes one checks.
     */
    public function delete(int $groupId): void;

    /**
     * The groups in the trash, the latest deleted first.
     *
     * @return Collection<int, CustomerGroupData>
     */
    public function findTrashed(): Collection;

    /**
     * Let go of a group in the trash for good.
     *
     * @return bool whether it went; false when it is not in the trash, or
     *              the database still holds a membership in it
     */
    public function deleteForever(int $groupId): bool;

    /**
     * The groups each customer is in, archived ones left out, in one query:
     * keyed by customer id, in the owner's order. A customer in no group is
     * absent.
     *
     * @param  list<int>  $customerIds
     * @return Collection<int, list<CustomerGroupData>>
     */
    public function getForCustomers(array $customerIds): Collection;

    /**
     * Put one customer in exactly these groups — added to the new ones, taken
     * out of the others. Groups that are archived are left as they are, in
     * or out: the choice a screen offers never shows them.
     *
     * @param  list<int>  $groupIds
     */
    public function saveMembership(int $customerId, array $groupIds, int $changedBy): void;

    /**
     * Add several customers to one group, or take them out of it. Adding
     * someone already in it, or taking out someone who is not, changes
     * nothing.
     *
     * @param  list<int>  $customerIds
     */
    public function saveGroupMembers(int $groupId, array $customerIds, bool $add, int $changedBy): void;
}
