<?php

namespace App\Modules\Customers\Domain\Services;

use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Domain\Actions\CreateCustomer;
use App\Modules\Customers\Domain\Actions\UpdateCustomer;
use Illuminate\Container\Attributes\Scoped;
use LogicException;

/**
 * The published directory, implemented over this module's own actions.
 *
 * Deliberately thin, like the ledger: other modules depend on the interface,
 * and the actions behind it stay free to change.
 */
#[Scoped]
final readonly class CustomerDirectory implements CustomerDirectoryInterface
{
    public function __construct(
        private CreateCustomer $create,
        private UpdateCustomer $update,
    ) {}

    public function add(CustomerData $customer): CustomerData
    {
        // A login handed in here would bypass the step that owns linking one.
        if ($customer->id !== null || $customer->userId !== null) {
            throw new LogicException('A customer is added with neither an id nor a login; portal access is granted on its own.');
        }

        return $this->create->handle([
            'name' => $customer->name,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'notes' => $customer->notes,
        ]);
    }

    public function update(CustomerData $customer): CustomerData
    {
        return $this->update->handle($customer);
    }
}
