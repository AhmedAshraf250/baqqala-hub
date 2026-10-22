<?php

namespace App\Admin\Contracts\Search;

/**
 * A module that the header's quick search finds things in.
 *
 * The provider only names its sources; the shell resolves them when someone
 * searches, and shows each group only to whoever holds its permission.
 */
interface ProvidesAdminSearchInterface
{
    /**
     * @return list<class-string<SearchSourceInterface>>
     */
    public function adminSearchSources(): array;
}
