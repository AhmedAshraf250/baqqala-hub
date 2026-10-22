<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Database\Models\Product;

/**
 * Takes a product off the shelf, or puts it back.
 *
 * An archived product leaves the picker and stays in every record that names
 * it. It comes back only onto a shelf that is itself in use: a product on an
 * archived category would be offered with no tile to find it under.
 */
final class SetProductArchived
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @return bool whether it changed; false when it would come back onto an archived shelf
     */
    public function handle(int $productId, bool $archived): bool
    {
        $product = Product::query()->with('category')->findOrFail($productId);

        if (! $archived && $product->category?->is_active === false) {
            return false;
        }

        if ($product->is_active === ! $archived) {
            return true;
        }

        $product->is_active = ! $archived;
        $product->save();

        $this->audit->record('catalog.product', $product->id, $product->name, 'catalog::module.audit.product', $archived ? AuditAction::Archived : AuditAction::Restored);

        return true;
    }
}
