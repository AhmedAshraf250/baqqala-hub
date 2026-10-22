<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Database\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a category that holds nothing — into the trash, where it can be
 * brought back. Nothing means nothing: no product and no category under it,
 * archived or in the trash themselves, since either could be brought back
 * into a category that is gone. One that holds something is archived
 * instead.
 */
final class DeleteCategory
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @return bool whether it went to the trash
     */
    public function handle(int $categoryId): bool
    {
        return DB::transaction(function () use ($categoryId): bool {
            $category = Category::query()->lockForUpdate()->findOrFail($categoryId);

            if ($category->children()->withTrashed()->exists() || $category->products()->withTrashed()->exists()) {
                return false;
            }

            $category->delete();
            $this->audit->record('catalog.category', $categoryId, $category->name, 'catalog::module.audit.category', AuditAction::Deleted);

            return true;
        });
    }
}
