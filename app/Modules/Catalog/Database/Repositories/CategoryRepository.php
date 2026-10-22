<?php

namespace App\Modules\Catalog\Database\Repositories;

use App\Modules\Catalog\Contracts\CategoryRepositoryInterface;
use App\Modules\Catalog\Contracts\Data\CategoryData;
use App\Modules\Catalog\Database\Models\Category;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * Categories, read from this module's own table.
 */
#[Scoped]
final class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * @return Collection<int, CategoryData>
     */
    public function getAll(bool $includeArchived = false): Collection
    {
        return Category::query()
            ->when(! $includeArchived, static fn ($query) => $query->active())
            ->inOrder()
            ->get()
            ->mapWithKeys(static fn (Category $category): array => [$category->getKey() => $category->toData()]);
    }
}
