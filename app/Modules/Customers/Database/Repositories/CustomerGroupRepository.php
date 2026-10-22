<?php

namespace App\Modules\Customers\Database\Repositories;

use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Database\Models\CustomerGroup;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer groups and their members, stored in this module's own tables.
 *
 * One instance per request: every caller in the request shares it.
 */
#[Scoped]
final class CustomerGroupRepository implements CustomerGroupRepositoryInterface
{
    private const string MEMBERS = 'customer_group_members';

    /**
     * @return Collection<int, CustomerGroupData>
     */
    public function getAll(bool $includeArchived = false): Collection
    {
        return CustomerGroup::query()
            ->withCount('members')
            ->when(! $includeArchived, static fn (Builder $query) => $query->notArchived())
            ->inOrder()
            ->get()
            ->mapWithKeys(static fn (CustomerGroup $group): array => [$group->getKey() => $group->toData()]);
    }

    public function find(int $groupId): ?CustomerGroupData
    {
        return CustomerGroup::query()->withCount('members')->find($groupId)?->toData();
    }

    public function save(CustomerGroupData $group): CustomerGroupData
    {
        $record = $group->id === null
            ? (new CustomerGroup)->forceFill(['created_by' => $group->createdBy])
            : CustomerGroup::withTrashed()->findOrFail($group->id);

        $record->fill([
            'name' => $group->name,
            'color' => $group->color,
            'icon' => $group->icon,
            'sort_order' => $group->sortOrder,
            'archived_at' => $group->archivedAt,
        ])->save();

        if ($record->trashed() && $group->deletedAt === null) {
            $record->restore();
        }

        return $record->loadCount('members')->toData();
    }

    public function delete(int $groupId): void
    {
        CustomerGroup::query()->findOrFail($groupId)->delete();
    }

    /**
     * @return Collection<int, CustomerGroupData>
     */
    public function findTrashed(): Collection
    {
        return CustomerGroup::onlyTrashed()
            ->latest('deleted_at')
            ->get()
            ->mapWithKeys(static fn (CustomerGroup $group): array => [$group->getKey() => $group->toData()]);
    }

    public function deleteForever(int $groupId): bool
    {
        $group = CustomerGroup::onlyTrashed()->find($groupId);

        if ($group === null) {
            return false;
        }

        try {
            return (bool) $group->forceDelete();
        } catch (QueryException) {
            return false;
        }
    }

    /**
     * @param  list<int>  $customerIds
     * @return Collection<int, list<CustomerGroupData>>
     */
    public function getForCustomers(array $customerIds): Collection
    {
        if ($customerIds === []) {
            return collect();
        }

        return CustomerGroup::query()
            ->join(self::MEMBERS, self::MEMBERS.'.group_id', '=', 'customer_groups.id')
            ->whereIn(self::MEMBERS.'.customer_id', $customerIds)
            ->notArchived()
            ->inOrder()
            ->select(['customer_groups.*', self::MEMBERS.'.customer_id as member_id'])
            ->get()
            ->groupBy(static fn (CustomerGroup $group): int => (int) $group->getAttribute('member_id'))
            ->map(static fn (Collection $groups): array => array_values($groups->map(static fn (CustomerGroup $group): CustomerGroupData => $group->toData())->all()));
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function saveMembership(int $customerId, array $groupIds, int $changedBy): void
    {
        DB::transaction(function () use ($customerId, $groupIds, $changedBy): void {
            $offered = CustomerGroup::query()->notArchived()->pluck('id')->map(intval(...))->all();
            $wanted = array_values(array_intersect($groupIds, $offered));

            DB::table(self::MEMBERS)
                ->where('customer_id', $customerId)
                ->whereIn('group_id', array_values(array_diff($offered, $wanted)))
                ->delete();

            $this->add(array_map(static fn (int $groupId): array => [$groupId, $customerId], $wanted), $changedBy);
        });
    }

    /**
     * @param  list<int>  $customerIds
     */
    public function saveGroupMembers(int $groupId, array $customerIds, bool $add, int $changedBy): void
    {
        if (! $add) {
            DB::table(self::MEMBERS)->where('group_id', $groupId)->whereIn('customer_id', $customerIds)->delete();

            return;
        }

        $this->add(array_map(static fn (int $customerId): array => [$groupId, $customerId], $customerIds), $changedBy);
    }

    /**
     * Store memberships, leaving any that already exist as they were — with
     * who added them first, and when.
     *
     * @param  list<array{0: int, 1: int}>  $pairs  Group id, customer id.
     */
    private function add(array $pairs, int $changedBy): void
    {
        if ($pairs === []) {
            return;
        }

        $now = now();

        DB::table(self::MEMBERS)->insertOrIgnore(array_map(static fn (array $pair): array => [
            'group_id' => $pair[0],
            'customer_id' => $pair[1],
            'created_at' => $now,
            'created_by' => $changedBy,
        ], $pairs));
    }
}
