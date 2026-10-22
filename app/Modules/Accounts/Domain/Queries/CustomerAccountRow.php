<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Foundation\Money\Money;
use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Data\CustomerGroupData;
use Carbon\CarbonImmutable;

/**
 * One line of the customer list: who, where their account stands, what
 * happened last, and what deserves a warning — read in a fixed handful of
 * queries for the whole page.
 *
 * The warnings are worked out when the list is read, never stored: owing and
 * not having paid for `lateDays` (since the last payment, or since the debt
 * began when there was none), owing past the limit, and owing with no entry
 * for `dormantDays`. A settled account that went quiet is nobody's concern,
 * so it carries none.
 */
final readonly class CustomerAccountRow
{
    /**
     * @param  list<CustomerGroupData>  $groups
     */
    public function __construct(
        public CustomerData $customer,
        public array $groups,
        public Money $outstanding,
        public Money $creditLimit,
        public ?int $lastEntryId,
        public ?EntryKind $lastEntryKind,
        public ?Money $lastEntryAmount,
        public ?CarbonImmutable $lastEntryAt,
        public bool $hasPaid,
        public ?int $lateDays,
        public ?int $dormantDays,
    ) {}

    public function customerId(): int
    {
        return (int) $this->customer->id;
    }

    public function isOverLimit(): bool
    {
        return ! $this->creditLimit->isZero() && $this->outstanding->isGreaterThan($this->creditLimit);
    }

    /**
     * How far past the limit the balance is; zero when it is not.
     */
    public function overLimitBy(): Money
    {
        return $this->isOverLimit() ? $this->outstanding->minus($this->creditLimit) : Money::zero();
    }

    public function hasWarnings(): bool
    {
        return $this->lateDays !== null || $this->dormantDays !== null || $this->isOverLimit();
    }
}
