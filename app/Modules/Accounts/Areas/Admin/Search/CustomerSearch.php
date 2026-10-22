<?php

namespace App\Modules\Accounts\Areas\Admin\Search;

use App\Admin\Contracts\Authorization\PermissionInterface;
use App\Admin\Contracts\Search\SearchSourceInterface;
use App\Admin\Search\SearchAction;
use App\Admin\Search\SearchResult;
use App\Foundation\Area\Area;
use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use Illuminate\Support\Collection;

/**
 * Customers in the header's quick search: each with the last digits of their
 * phone, their groups, and where their account stands — and, for whoever
 * records entries, «أخذ» and «دفع» for them one key away.
 *
 * Before anything is typed, whoever was served today; when nobody matches,
 * the offer to add them.
 */
final class CustomerSearch implements SearchSourceInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerGroupRepositoryInterface $groups,
        private readonly LocalClock $clock,
    ) {}

    public function label(): string
    {
        return 'accounts::module.search.customers';
    }

    public function permission(): PermissionInterface
    {
        return AccountPermission::View;
    }

    /**
     * @return list<SearchResult>
     */
    public function search(string $term, int $limit): array
    {
        return $this->results($this->customers->search($term, $limit)->values());
    }

    /**
     * Whoever was served since the business day began, the latest first.
     *
     * @return list<SearchResult>
     */
    public function suggestions(int $limit): array
    {
        [$start] = $this->clock->dayBounds($this->clock->today());

        $ids = array_values(CustomerAccount::query()
            ->where('last_entry_at', '>=', $start)
            ->orderByDesc('last_entry_at')
            ->limit($limit)
            ->pluck('customer_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());

        $found = $this->customers->findMany($ids);

        return $this->results(collect($ids)->map(static fn (int $id): ?CustomerData => $found->get($id))->filter()->values());
    }

    public function fallback(string $term): ?SearchResult
    {
        if (Area::Admin->user()?->can(CustomerPermission::Create->value) !== true) {
            return null;
        }

        return new SearchResult(
            title: __('accounts::module.search.add', ['name' => $term]),
            url: route('admin.accounts.index', ['q' => $term]),
            subtitle: __('accounts::module.search.add_hint'),
            icon: 'person-plus',
        );
    }

    /**
     * @param  Collection<int, CustomerData>  $customers
     * @return list<SearchResult>
     */
    private function results(Collection $customers): array
    {
        $ids = array_values($customers->map(static fn (CustomerData $customer): int => (int) $customer->id)->all());

        if ($ids === []) {
            return [];
        }

        $accounts = CustomerAccount::query()->whereIn('customer_id', $ids)->get()->keyBy('customer_id');
        $groups = $this->groups->getForCustomers($ids);
        $posts = Area::Admin->user()?->can(AccountPermission::Post->value) === true;

        return array_values($customers->map(function (CustomerData $customer) use ($accounts, $groups, $posts): SearchResult {
            $outstanding = $accounts->get($customer->id)->outstanding ?? Money::zero();
            $about = array_filter([
                $customer->phone === null ? null : '…'.substr($customer->phone, -4),
                implode('، ', array_map(static fn (CustomerGroupData $group): string => $group->name, $groups->get($customer->id) ?? [])),
            ]);

            return new SearchResult(
                title: $customer->name,
                url: route('admin.accounts.show', $customer->id),
                subtitle: $about === [] ? null : implode(' · ', $about),
                badge: $this->inWords($outstanding),
                tone: match (true) {
                    $outstanding->isPositive() => 'debit',
                    $outstanding->isNegative() => 'credit',
                    default => null,
                },
                icon: 'person',
                actions: $posts ? [
                    new SearchAction('+', __('accounts::module.list.charge'), route('admin.accounts.show', ['customer' => $customer->id, 'entry' => 'charge']), 'debit', 'bag-plus'),
                    new SearchAction('-', __('accounts::module.list.payment'), route('admin.accounts.show', ['customer' => $customer->id, 'entry' => 'payment']), 'credit', 'cash-coin'),
                ] : [],
            );
        })->all());
    }

    private function inWords(Money $balance): string
    {
        return match (true) {
            $balance->isPositive() => __('accounts::module.balance.owes').' '.$balance->toDisplayString(),
            $balance->isNegative() => __('accounts::module.balance.in_credit').' '.$balance->negated()->toDisplayString(),
            default => __('accounts::module.balance.settled'),
        };
    }
}
