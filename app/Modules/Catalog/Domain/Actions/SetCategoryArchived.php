<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Database\Models\Category;

/**
 * Takes a category out of use, or puts it back.
 *
 * Only an empty shelf is archived: one still holding products the counter
 * sells, or a sub-category in use, would take them out of the picker without
 * a word. And a sub-category comes back only under a category in use.
 */
final class SetCategoryArchived
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @return bool whether it changed
     */
    public function handle(int $categoryId, bool $archived): bool
    {
        $category = Category::query()->with('parent')->findOrFail($categoryId);

        if ($archived && ($category->products()->where('is_active', true)->exists() || $category->children()->where('is_active', true)->exists())) {
            return false;
        }

        if (! $archived && $category->parent?->is_active === false) {
            return false;
        }

        if ($category->is_active === ! $archived) {
            return true;
        }

        $category->is_active = ! $archived;
        $category->save();

        $this->audit->record('catalog.category', $category->id, $category->name, 'catalog::module.audit.category', $archived ? AuditAction::Archived : AuditAction::Restored);

        return true;
    }
}
