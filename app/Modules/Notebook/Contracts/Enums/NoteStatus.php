<?php

namespace App\Modules\Notebook\Contracts\Enums;

/**
 * Where a note stands: still waiting to be posted, posted to an account, or
 * thrown away.
 */
enum NoteStatus: string
{
    case Pending = 'pending';

    case Posted = 'posted';

    case Discarded = 'discarded';

    public function label(): string
    {
        return __('notebook::module.status.'.$this->value);
    }
}
