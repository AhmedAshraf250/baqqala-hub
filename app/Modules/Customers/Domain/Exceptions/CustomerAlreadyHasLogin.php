<?php

namespace App\Modules\Customers\Domain\Exceptions;

use App\Modules\Customers\Contracts\Data\CustomerData;
use RuntimeException;

/**
 * Thrown when a customer who can already sign in is given a second login.
 */
final class CustomerAlreadyHasLogin extends RuntimeException
{
    public function __construct(public readonly CustomerData $customer)
    {
        parent::__construct(sprintf('Customer [%d] is already linked to a login.', $customer->id));
    }
}
