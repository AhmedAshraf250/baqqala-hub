<?php

namespace App\Modules\Accounts\Contracts\Exceptions;

use RuntimeException;

/**
 * Thrown when a charge names a product that is not in the catalog, or was
 * archived after the person picked it.
 */
final class ProductUnavailable extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product [{$productId}] is not for sale.");
    }
}
