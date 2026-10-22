<?php

namespace App\Modules\Accounts\Contracts\Enums;

/**
 * What to do with a payment larger than what is owed.
 *
 * A customer owing 420 hands over 500. Either the 80 went back as change —
 * so 420 is what was paid — or the shop keeps it against what they take
 * next. Only the person at the counter knows which, so a payment says it, and
 * one that does not is refused rather than guessed.
 */
enum OverpaymentChoice: string
{
    /** Refuse a payment larger than what is owed. */
    case Refuse = 'refuse';

    /** Record only what was owed; the rest went back as change. */
    case RecordOwedOnly = 'owed_only';

    /** Record all of it; the rest stays as credit, if the shop takes deposits. */
    case KeepAsCredit = 'keep_as_credit';
}
