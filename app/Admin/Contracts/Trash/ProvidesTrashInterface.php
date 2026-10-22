<?php

namespace App\Admin\Contracts\Trash;

/**
 * A module whose deletions go to the trash, where they can be brought back
 * until someone lets go of them for good.
 *
 * The provider only names its sources; the shell resolves them when the
 * trash is opened, and shows each only to whoever holds its permission.
 */
interface ProvidesTrashInterface
{
    /**
     * @return list<class-string<TrashSourceInterface>>
     */
    public function adminTrashSources(): array;
}
