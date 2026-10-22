<?php

namespace App\Modules\Catalog\Domain\Actions;

use App\Foundation\Audit\AuditAction;
use App\Foundation\Audit\AuditTrail;
use App\Modules\Catalog\Database\Models\Product;
use App\Modules\Catalog\Domain\Exceptions\ProbeUndone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a product nobody ever bought — into the trash, where it can be
 * brought back until someone lets go of it for good.
 *
 * One that was sold is named by the records of the sale, and is archived
 * instead. Which records those are, the catalog never needs to know: the
 * database knows every module's foreign keys, so it is asked — the product
 * is removed in a savepoint that is always undone, and a refusal means
 * something still names it.
 */
final class DeleteProduct
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @return bool whether it went to the trash; false when a record still names it
     */
    public function handle(int $productId): bool
    {
        return DB::transaction(function () use ($productId): bool {
            $product = Product::query()->whereKey($productId)->lockForUpdate()->first();

            if ($product === null || self::isNamedElsewhere($productId)) {
                return false;
            }

            $product->delete();
            $this->audit->record('catalog.product', $productId, $product->name, 'catalog::module.audit.product', AuditAction::Deleted);

            return true;
        });
    }

    /**
     * Whether another record still names the product: the database refuses
     * to let it go, in a savepoint undone whatever it answers.
     */
    public static function isNamedElsewhere(int $productId): bool
    {
        try {
            DB::transaction(static function () use ($productId): void {
                Product::withTrashed()->whereKey($productId)->forceDelete();

                throw new ProbeUndone;
            });
        } catch (ProbeUndone) {
            return false;
        } catch (QueryException $refused) {
            if (self::isHeldByAnotherRecord($refused)) {
                return true;
            }

            throw $refused;
        }
    }

    /**
     * A foreign key refused the deletion: SQLSTATE 23000 on SQLite and
     * MySQL, 23503 on PostgreSQL.
     */
    public static function isHeldByAnotherRecord(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23503'], true);
    }
}
