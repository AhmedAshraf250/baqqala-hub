<?php

namespace App\Modules\Catalog\Contracts\Data;

/**
 * A category, as other modules see it: a shelf the product picker offers as
 * a tile, in the owner's order.
 *
 * `icon` is a Bootstrap Icons name without its `bi-` prefix. `parentId` is
 * set for a sub-category; there are at most two levels.
 */
final readonly class CategoryData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $parentId = null,
        public ?string $icon = null,
        public int $sortOrder = 0,
        public bool $isActive = true,
    ) {}
}
