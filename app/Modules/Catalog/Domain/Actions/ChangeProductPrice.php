<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditChange;
use App\Foundation\Audit\AuditTrail;
use App\Foundation\Money\Money;
use App\Modules\Catalog\Database\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Gives a product a new price, from the moment it is saved.
 *
 * Nothing already recorded changes: every line of a charge keeps the price it
 * was sold at and the catalog's price at the time. The change goes into the
 * change log.
 */
final class ChangeProductPrice
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @return Money the price it had before
     */
    public function handle(int $productId, Money $price): Money
    {
        return DB::transaction(function () use ($productId, $price): Money {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $before = $product->price;

            $product->price = $price;
            $product->save();

            if (! $before->equals($price)) {
                $this->audit->record('catalog.product', $product->id, $product->name, 'catalog::module.audit.product', AuditAction::Updated, [
                    new AuditChange('catalog::module.product.price', $before->toDisplayString(), $price->toDisplayString()),
                ]);
            }

            return $before;
        });
    }
}
