<?php

namespace App\Modules\Customers\Contracts\Enums;

/**
 * The colour a customer group's chip is drawn in.
 *
 * Eight, never a free colour: each is a pair of background and text tuned to
 * stay readable in light and dark, and a colour picked freely would not be.
 * Published as a key; the screen decides what the key looks like.
 */
enum GroupColor: string
{
    case Blue = 'blue';

    case Green = 'green';

    case Amber = 'amber';

    case Red = 'red';

    case Purple = 'purple';

    case Teal = 'teal';

    case Pink = 'pink';

    case Gray = 'gray';

    /**
     * The colour's name, for a screen reader and a colour picker's choices.
     */
    public function label(): string
    {
        return __($this->labelKey());
    }

    /**
     * The translation key of its name, for whatever shows it later in the
     * reader's language — a line of the change log.
     */
    public function labelKey(): string
    {
        return 'customers::module.group_color.'.$this->value;
    }
}
