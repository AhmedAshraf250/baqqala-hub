<?php

namespace App\Modules\Catalog\Areas\Admin\Trash;

use App\Admin\Contracts\Trash\TrashSourceInterface;
use App\Admin\Trash\TrashItem;
use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Authorization\CatalogPermission;
use App\Modules\Catalog\Database\Models\Category;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Actions\DeleteProduct;
use Illuminate\Support\Facades\DB;

/**
 * Products in the trash: never sold, deleted, and kept to be brought back —
 * into their category, unless that is in the trash too.
 */
final readonly class ProductTrash implements TrashSourceInterface
{
    public function __construct(private AuditTrail $audit) {}

    public function label(): string
    {
        return 'catalog::module.trash.products';
    }

    public function permission(): CatalogPermission
    {
        return CatalogPermission::Manage;
    }

    public function items(): array
    {
        $products = Product::onlyTrashed()->latest('deleted_at')->limit(200)->get();
        $categories = Category::withTrashed()->whereKey($products->pluck('category_id')->unique()->all())->pluck('name', 'id');
        $by = $this->audit->lastBy('catalog.product', AuditAction::Deleted, array_values(array_map(intval(...), $products->modelKeys())));

        return array_values($products->map(static fn (Product $product): TrashItem => new TrashItem(
            id: $product->id,
            title: $product->name,
            subtitle: trim(($categories[$product->category_id] ?? '').' · '.$product->price->toDisplayString(), ' ·'),
            deletedAt: $product->deleted_at ?? now()->toImmutable(),
            deletedBy: $by[$product->id] ?? null,
            icon: 'box-seam',
        ))->all());
    }

    public function restore(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $product = Product::onlyTrashed()->whereKey($id)->lockForUpdate()->first();

            if ($product === null || Category::query()->whereKey($product->category_id)->doesntExist()) {
                return false;
            }

            $product->restore();
            $this->audit->record('catalog.product', $id, $product->name, 'catalog::module.audit.product', AuditAction::Restored);

            return true;
        });
    }

    public function purge(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $product = Product::onlyTrashed()->whereKey($id)->lockForUpdate()->first();

            if ($product === null || DeleteProduct::isNamedElsewhere($id)) {
                return false;
            }

            $product->forceDelete();
            $this->audit->record('catalog.product', $id, $product->name, 'catalog::module.audit.product', AuditAction::Purged);

            return true;
        });
    }
}
