<?php

namespace App\Foundation\Audit;

/**
 * What happened to the thing a change-log entry is about.
 */
enum AuditAction: string
{
    case Created = 'created';

    case Updated = 'updated';

    case Archived = 'archived';

    case Restored = 'restored';

    /** Moved to the trash: kept, and can be brought back. */
    case Deleted = 'deleted';

    /** Taken out of the trash for good. */
    case Purged = 'purged';

    public function label(): string
    {
        return __('foundation.audit.action.'.$this->value);
    }
}
