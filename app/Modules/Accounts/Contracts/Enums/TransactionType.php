<?php

namespace App\Modules\Accounts\Contracts\Enums;

use App\Foundation\Money\Money;

/**
 * What a ledger entry did to the customer's account.
 *
 * Published, so it carries meaning and never presentation: how an entry is
 * coloured is a decision for the screen that draws it, and the admin area
 * (Bootstrap) and the portal (Tailwind) would not agree on a class name.
 *
 * The standard accounting pair, because it is the one an accountant, an audit,
 * and any other ledger already speak — and because it is neutral: a school
 * billing fees or a clinic billing a visit posts the same two kinds of entry as
 * a shop handing over goods.
 *
 * `outstanding` is what the customer owes, so a debit raises it and a credit
 * lowers it. What the counter actually reads is the Arabic: «عليه» and «له».
 */
enum TransactionType: string
{
    /** Taken and not paid for. The customer now owes more. */
    case Debit = 'debit';

    /** Money handed over. The customer now owes less. */
    case Credit = 'credit';

    /**
     * Apply an amount of this type to an outstanding total.
     */
    public function applyTo(Money $outstanding, Money $amount): Money
    {
        return match ($this) {
            self::Debit => $outstanding->plus($amount),
            self::Credit => $outstanding->minus($amount),
        };
    }

    /**
     * The inverse type, used when reversing a posted entry.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::Debit => self::Credit,
            self::Credit => self::Debit,
        };
    }

    /**
     * Whether this type increases what the customer owes.
     */
    public function increasesDebt(): bool
    {
        return $this === self::Debit;
    }

    /**
     * The short label on a statement line — «عليه» / «له».
     */
    public function label(): string
    {
        return __('accounts::module.transaction_type.'.$this->value);
    }

    /**
     * What the entry describes happening, for a form or a log line.
     */
    public function actionLabel(): string
    {
        return __('accounts::module.transaction_action.'.$this->value);
    }
}
