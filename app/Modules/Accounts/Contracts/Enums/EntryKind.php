<?php

namespace App\Modules\Accounts\Contracts\Enums;

/**
 * What a ledger entry records happening, beside {@see TransactionType}, the
 * way it moved the balance.
 *
 * Most kinds always move it the same way: goods taken raise what is owed,
 * money paid lowers it. Two do not: an opening balance brought from the paper
 * book can be owed either way, and a reversal moves it opposite to whatever it
 * cancels. So the direction is stored beside the kind, never derived from it.
 */
enum EntryKind: string
{
    /** Goods taken on account — «أخذ». */
    case Charge = 'charge';

    /** Money handed over — «دفع». */
    case Payment = 'payment';

    /** What the paper book said was owed, either way, when the account moved here. */
    case OpeningBalance = 'opening_balance';

    /** Part of a debt forgiven — «سامحته في الباقي». Lowers what is owed, never below zero. */
    case Discount = 'discount';

    /** Goods brought back. */
    case Return = 'return';

    /** Cancels one earlier entry, moving the balance back by its amount. */
    case Reversal = 'reversal';

    /**
     * The direction this kind always moves the balance, or null when it can
     * go either way and the entry says which.
     */
    public function direction(): ?TransactionType
    {
        return match ($this) {
            self::Charge => TransactionType::Debit,
            self::Payment, self::Discount, self::Return => TransactionType::Credit,
            self::OpeningBalance, self::Reversal => null,
        };
    }

    /**
     * Whether an entry of this kind may be voided or corrected. A reversal is
     * the correction of something else; to undo it, the original is entered
     * again.
     */
    public function isCorrectable(): bool
    {
        return $this !== self::Reversal;
    }

    /**
     * What the counter calls it.
     */
    public function label(): string
    {
        return __('accounts::module.kind.'.$this->value);
    }
}
