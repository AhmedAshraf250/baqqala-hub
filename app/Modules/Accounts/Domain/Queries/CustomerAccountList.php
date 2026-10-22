<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;
use App\Foundation\Time\LocalClock;
use App\Modules\Accounts\Database\Models\CustomerAccount;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerCriteria;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * The customer list: accounts narrowed, ordered, and paged here, the people
 * on the page read from Customers.
 *
 * The two modules never meet in one query. When the list is narrowed by who
 * someone is — a search, a group — Customers answers with ids first; the
 * accounts are then filtered, sorted, and paged on their own table, and the
 * page's names and groups are read in one query each. A fixed handful of
 * queries however many customers there are, and the price is that the list
 * cannot sort by name.
 */
final class CustomerAccountList
{
    /**
     * Since when an owing account has gone unpaid: the later of its last
     * payment and the start of its debt — a payment made before this debt
     * began does not make it recent.
     */
    private const string OWING_SINCE = '(CASE WHEN last_payment_at IS NULL OR last_payment_at < in_debt_since THEN in_debt_since ELSE last_payment_at END)';

    /**
     * The ids Customers gave for each set of criteria, so the page and its
     * totals ask once between them.
     *
     * @var array<string, list<int>>
     */
    private array $matching = [];

    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly CustomerGroupRepositoryInterface $groups,
        private readonly LocalClock $clock,
    ) {}

    /**
     * One page of the list.
     *
     * @return LengthAwarePaginator<int, CustomerAccountRow>
     */
    public function page(CustomerAccountFilter $filter, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->narrowed($filter);

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $perPage, Paginator::resolveCurrentPage());
        }

        $accounts = $this->ordered($query, $filter->sort)->with('lastEntry')->paginate($perPage);

        $ids = array_values(array_map(static fn (CustomerAccount $account): int => $account->customer_id, $accounts->items()));
        $people = $this->customers->findMany($ids);
        $groups = $this->groups->getForCustomers($ids);
        $now = CarbonImmutable::now();

        $rows = array_map(
            fn (CustomerAccount $account): CustomerAccountRow => $this->row(
                $account,
                $people->get($account->customer_id) ?? new CustomerData($account->customer_id, '#'.$account->customer_id),
                $groups->get($account->customer_id) ?? [],
                $now,
            ),
            $accounts->items(),
        );

        return new LengthAwarePaginator($rows, $accounts->total(), $accounts->perPage(), $accounts->currentPage());
    }

    /**
     * The totals over everything the filter keeps, in one query.
     */
    public function totals(CustomerAccountFilter $filter): CustomerAccountTotals
    {
        $query = $this->narrowed($filter);

        if ($query === null) {
            return CustomerAccountTotals::none();
        }

        $sums = $query->toBase()->selectRaw(
            'COUNT(*) AS accounts,'
            .' COALESCE(SUM(CASE WHEN outstanding > 0 THEN 1 ELSE 0 END), 0) AS owing,'
            .' COALESCE(SUM(CASE WHEN outstanding > 0 THEN outstanding ELSE 0 END), 0) AS owed,'
            .' COALESCE(SUM(CASE WHEN outstanding < 0 THEN 1 ELSE 0 END), 0) AS in_credit,'
            .' COALESCE(SUM(CASE WHEN outstanding < 0 THEN -outstanding ELSE 0 END), 0) AS held,'
            .' COALESCE(SUM(CASE WHEN outstanding = 0 THEN 1 ELSE 0 END), 0) AS settled,'
            .' COALESCE(SUM(CASE WHEN outstanding > 0 AND '.self::OWING_SINCE.' < ? THEN 1 ELSE 0 END), 0) AS late',
            [CarbonImmutable::now()->subDays(self::lateAfterDays())->toDateTimeString()],
        )->first();

        return new CustomerAccountTotals(
            accounts: (int) ($sums->accounts ?? 0),
            owing: (int) ($sums->owing ?? 0),
            owed: Money::fromMinorUnits((int) ($sums->owed ?? 0)),
            inCredit: (int) ($sums->in_credit ?? 0),
            heldForThem: Money::fromMinorUnits((int) ($sums->held ?? 0)),
            settled: (int) ($sums->settled ?? 0),
            late: (int) ($sums->late ?? 0),
        );
    }

    /**
     * Whether the shop has any customer at all — what decides between «no
     * customers yet» and «nobody matches».
     */
    public function hasAnyAccount(): bool
    {
        return CustomerAccount::query()->exists();
    }

    /**
     * The accounts the filter keeps, or null when the people it names are
     * nobody.
     *
     * @return Builder<CustomerAccount>|null
     */
    private function narrowed(CustomerAccountFilter $filter): ?Builder
    {
        $query = CustomerAccount::query();

        if ($filter->narrowsPeople()) {
            $ids = $this->matchingPeople($filter);

            if ($ids === []) {
                return null;
            }

            $query->whereIn('customer_id', $ids);
        }

        $lateSince = CarbonImmutable::now()->subDays(self::lateAfterDays());
        $dormantSince = CarbonImmutable::now()->subDays(self::dormantAfterDays());

        match ($filter->balance) {
            BalanceFilter::Owes => $query->where('outstanding', '>', 0),
            BalanceFilter::Settled => $query->where('outstanding', 0),
            BalanceFilter::InCredit => $query->where('outstanding', '<', 0),
            BalanceFilter::OverLimit => $query->where('credit_limit', '>', 0)->whereColumn('outstanding', '>', 'credit_limit'),
            null => null,
        };

        match ($filter->activity) {
            ActivityFilter::Active => $query->where('last_entry_at', '>=', $dormantSince),
            ActivityFilter::Dormant => $query->where('last_entry_at', '<', $dormantSince),
            // Owing, and unpaid for longer than the threshold.
            ActivityFilter::Late => $query->where('outstanding', '>', 0)->whereRaw(self::OWING_SINCE.' < ?', [$lateSince->toDateTimeString()]),
            null => null,
        };

        match ($filter->since) {
            LastEntrySince::Today => $query->where('last_entry_at', '>=', $this->clock->dayBounds($this->clock->today())[0]),
            LastEntrySince::Week => $query->where('last_entry_at', '>=', CarbonImmutable::now()->subDays(7)),
            LastEntrySince::Month => $query->where('last_entry_at', '>=', CarbonImmutable::now()->subDays(30)),
            null => null,
        };

        return $query;
    }

    /**
     * @return list<int>
     */
    private function matchingPeople(CustomerAccountFilter $filter): array
    {
        $criteria = new CustomerCriteria(
            term: $filter->term === '' ? null : $filter->term,
            groupIds: $filter->groupId === null ? [] : [$filter->groupId],
        );

        return $this->matching[$filter->term.'|'.$filter->groupId] ??= $this->customers->searchIds($criteria);
    }

    /**
     * @param  Builder<CustomerAccount>  $query
     * @return Builder<CustomerAccount>
     */
    private function ordered(Builder $query, AccountListSort $sort): Builder
    {
        // An account nobody has posted to yet has no latest entry and no debt,
        // and goes after those that do rather than before them.
        return match ($sort) {
            AccountListSort::Recent => $query->orderByRaw('last_entry_at IS NULL')->orderByDesc('last_entry_at')->orderByDesc('id'),
            AccountListSort::Largest => $query->orderByDesc('outstanding')->orderByDesc('id'),
            AccountListSort::OldestDebt => $query->orderByRaw('in_debt_since IS NULL')->orderBy('in_debt_since')->orderBy('id'),
            AccountListSort::Newest => $query->orderByDesc('id'),
        };
    }

    /**
     * @param  list<CustomerGroupData>  $groups
     */
    private function row(CustomerAccount $account, CustomerData $customer, array $groups, CarbonImmutable $now): CustomerAccountRow
    {
        $lateDays = null;
        $hasPaid = false;
        $dormantDays = null;

        if ($account->outstanding->isPositive() && $account->in_debt_since !== null) {
            $paidSinceDebt = $account->last_payment_at !== null && $account->last_payment_at->greaterThanOrEqualTo($account->in_debt_since);
            $quietSince = $paidSinceDebt ? $account->last_payment_at : $account->in_debt_since;
            $days = (int) $quietSince->diffInDays($now);

            if ($days >= self::lateAfterDays()) {
                $lateDays = $days;
                $hasPaid = $paidSinceDebt;
            }

            if ($account->last_entry_at !== null && (int) $account->last_entry_at->diffInDays($now) >= self::dormantAfterDays()) {
                $dormantDays = (int) $account->last_entry_at->diffInDays($now);
            }
        }

        $last = $account->lastEntry;

        return new CustomerAccountRow(
            customer: $customer,
            groups: $groups,
            outstanding: $account->outstanding,
            creditLimit: $account->credit_limit,
            lastEntryId: $account->last_entry_id,
            lastEntryKind: $last?->kind,
            lastEntryAmount: $last?->amount,
            lastEntryAt: $account->last_entry_at,
            hasPaid: $hasPaid,
            lateDays: $lateDays,
            dormantDays: $dormantDays,
        );
    }

    private static function lateAfterDays(): int
    {
        return max(1, app(AccountSettings::class)->lateAfterDays());
    }

    private static function dormantAfterDays(): int
    {
        return max(1, app(AccountSettings::class)->dormantAfterDays());
    }
}
