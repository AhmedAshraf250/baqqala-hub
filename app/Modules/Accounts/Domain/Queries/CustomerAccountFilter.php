<?php

namespace App\Modules\Accounts\Domain\Queries;

/**
 * What the customer list is narrowed to and how it is ordered — the one
 * value every filter on the screen becomes, read from and written to the
 * page's address, so a filtered list is a link that can be kept or sent.
 *
 * The search and the group are Customers' to answer; the rest is the
 * account's own.
 */
final readonly class CustomerAccountFilter
{
    public function __construct(
        public string $term = '',
        public ?int $groupId = null,
        public ?BalanceFilter $balance = null,
        public ?ActivityFilter $activity = null,
        public ?LastEntrySince $since = null,
        public AccountListSort $sort = AccountListSort::Recent,
    ) {}

    /**
     * The filter the screen's raw values describe, any unknown one dropped
     * rather than refused: an old link still opens.
     */
    public static function fromScreen(string $term, ?int $groupId, string $balance, string $activity, string $since, string $sort): self
    {
        return new self(
            term: trim($term),
            groupId: $groupId,
            balance: BalanceFilter::tryFrom($balance),
            activity: ActivityFilter::tryFrom($activity),
            since: LastEntrySince::tryFrom($since),
            sort: AccountListSort::tryFrom($sort) ?? AccountListSort::Recent,
        );
    }

    /**
     * Whether only some people are wanted — by who they are, or the group
     * they are in — which Customers is asked first.
     */
    public function narrowsPeople(): bool
    {
        return $this->term !== '' || $this->groupId !== null;
    }

    /**
     * Whether anything at all is narrowed, beyond the order.
     */
    public function narrows(): bool
    {
        return $this->narrowsPeople() || $this->balance !== null || $this->activity !== null || $this->since !== null;
    }
}
