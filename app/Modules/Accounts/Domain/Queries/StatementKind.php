<?php

namespace App\Modules\Accounts\Domain\Queries;

use App\Modules\Accounts\Contracts\Enums\EntryKind;
use App\Modules\Accounts\Database\Models\AccountTransaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which entries a statement shows, when not all of them.
 */
enum StatementKind: string
{
    /** «أخذ». */
    case Charges = 'charges';

    /** «دفع». */
    case Payments = 'payments';

    /** Voids, and what corrected entries became. */
    case Corrections = 'corrections';

    /** Opening balances and discounts. */
    case Adjustments = 'adjustments';

    public function label(): string
    {
        return __('accounts::module.statement.kind.'.$this->value);
    }

    /**
     * @param  Builder<AccountTransaction>  $query
     */
    public function narrow(Builder $query): void
    {
        match ($this) {
            self::Charges => $query->where('kind', EntryKind::Charge),
            self::Payments => $query->where('kind', EntryKind::Payment),
            self::Corrections => $query->where(static fn (Builder $query) => $query->where('kind', EntryKind::Reversal)->orWhereNotNull('replaces_id')),
            self::Adjustments => $query->whereIn('kind', [EntryKind::OpeningBalance, EntryKind::Discount, EntryKind::Return]),
        };
    }
}
