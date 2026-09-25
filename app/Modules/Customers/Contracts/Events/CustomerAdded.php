<?php

namespace App\Modules\Customers\Contracts\Events;

use App\Modules\Customers\Contracts\Data\CustomerData;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone was added to the shop's books.
 *
 * Announced so other modules can react without this one knowing they exist —
 * the Accounts module opens their running account on hearing it.
 *
 * Carries a {@see CustomerData}, not the model: a listener reads what
 * happened, it does not hold a live record it could change.
 *
 * Held until the outermost database transaction commits. A customer added
 * inside a larger operation that then rolls back was never added, and no
 * listener hears about them.
 */
final class CustomerAdded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly CustomerData $customer) {}
}
