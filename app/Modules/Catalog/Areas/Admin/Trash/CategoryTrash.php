<?php

namespace App\Modules\Catalog\Areas\Admin\Trash;

use App\Admin\Contracts\Trash\TrashSourceInterface;
use App\Admin\Trash\TrashItem;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Categories in the trash: empty when deleted, and kept to be brought back —
 * under their parent, unless that is in the trash too. One is let go of for
 * good only while nothing at all is in it.
 */
final readonly class CategoryTrash implements TrashSourceInterface
{
    public function __construct(private AuditTrail $audit) {}

    public function label(): string
    {
        return 'catalog::module.trash.categories';
    }

    public function permission(): CatalogPermission
    {
        return CatalogPermission::Manage;
    }

    public function items(): array
    {
        $categories = Category::onlyTrashed()->latest('deleted_at')->limit(200)->get();
        $parents = Category::withTrashed()->whereKey($categories->pluck('parent_id')->filter()->unique()->all())->pluck('name', 'id');
        $by = $this->audit->lastBy('catalog.category', AuditAction::Deleted, array_values(array_map(intval(...), $categories->modelKeys())));

        return array_values($categories->map(static fn (Category $category): TrashItem => new TrashItem(
            id: $category->id,
            title: $category->name,
            subtitle: $category->parent_id === null ? null : ($parents[$category->parent_id] ?? null),
            deletedAt: $category->deleted_at ?? now()->toImmutable(),
            deletedBy: $by[$category->id] ?? null,
            icon: $category->icon ?? 'tag',
        ))->all());
    }

    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $category = Category::onlyTrashed()->whereKey($id)->lockForUpdate()->first();

            if ($category === null || ($category->parent_id !== null && Category::query()->whereKey($category->parent_id)->doesntExist())) {
                return false;
            }

            $category->restore();
            $this->audit->record('catalog.category', $id, $category->name, 'catalog::module.audit.category', AuditAction::Restored);

            return true;
        });
    }

    public function purge(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $category = Category::onlyTrashed()->whereKey($id)->lockForUpdate()->first();

            if ($category === null
                || Category::withTrashed()->where('parent_id', $id)->exists()
                || Product::withTrashed()->where('category_id', $id)->exists()) {
                return false;
            }

            $category->forceDelete();
            $this->audit->record('catalog.category', $id, $category->name, 'catalog::module.audit.category', AuditAction::Purged);

            return true;
        });
    }
}
