<?php

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Contracts\Data\CategoryData;
use Illuminate\Support\Collection;

/**
 * The categories table, as data: fetch.
 */
interface CategoryRepositoryInterface
{
    /**
     * Every category, in the owner's order, keyed by id. Archived ones only
     * when asked for.
     *
     * @return Collection<int, CategoryData>
     */
    public function getAll(bool $includeArchived = false): Collection;
}
