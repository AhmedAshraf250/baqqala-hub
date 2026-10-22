<?php

namespace App\Modules\Accounts\Contracts\Enums;

/**
 * Why an entry was voided or corrected — from a short list, so a report can
 * count the mistakes by kind, with a note beside it for the rest.
 */
enum CorrectionReason: string
{
    /** Recorded twice. */
    case Duplicate = 'duplicate';

    /** The right goods, the wrong amount. */
    case WrongAmount = 'wrong_amount';

    /** Recorded on someone else's account. */
    case WrongCustomer = 'wrong_customer';

    /** The wrong products, or the wrong quantities of them. */
    case WrongItems = 'wrong_items';

    /** Taken back by whoever recorded it, within seconds. */
    case Undo = 'undo';

    /** Something else, said in the note. */
    case Other = 'other';

    public function label(): string
    {
        return __('accounts::module.correction_reason.'.$this->value);
    }
}
