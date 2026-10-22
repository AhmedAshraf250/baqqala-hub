<?php

namespace App\Modules\Catalog\Contracts;

use App\Modules\Catalog\Contracts\Data\ProductData;
use Illuminate\Support\Collection;

/**
 * The products table, as data: fetch.
 *
 * What another module selling from the catalog needs — the products it can
 * offer, and the current name, unit, and price of the ones it was handed —
 * and nothing that changes them. Adding a product or changing its price are
 * this module's own steps, behind its own screens.
 */
interface ProductRepositoryInterface
{
    /**
     * One product, archived or not, or null if there is no such record.
     */
    public function find(int $productId): ?ProductData;

    /**
     * Several at once, in one query, keyed by id, archived ones included —
     * a sale saved with a product that was archived meanwhile still has to
     * say what it was. Ids with no record are absent.
     *
     * @param  list<int>  $productIds
     * @return Collection<int, ProductData>
     */
    public function findMany(array $productIds): Collection;

    /**
     * Every product that may be sold today, in its category's order and then
     * by name.
     *
     * @return Collection<int, ProductData>
     */
    public function getActive(): Collection;

    /**
     * A value that changes whenever a product or a category does, so a
     * screen holding a copy of the catalog can ask whether it is still
     * current without fetching it again.
     */
    public function getCatalogVersion(): string;
}
