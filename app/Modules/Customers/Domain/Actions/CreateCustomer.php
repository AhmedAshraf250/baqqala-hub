<?php

namespace App\Modules\Customers\Domain\Actions;

use App\Foundation\Identity\Models\FrontendUser;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Events\CustomerAdded;
use App\Modules\Customers\Database\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Adds a person to the shop's books.
 *
 * The business step, not the storage one: it saves through the repository,
 * then announces {@see CustomerAdded} so whichever module cares can react.
 * This module does not know accounts exist — Accounts opens one on hearing it
 * — which is what lets it travel to a product that has no ledger.
 *
 * The login is optional. Whoever adds the customer at the counter passes none;
 * a portal login is granted later, and only if the shop decides to. When one is
 * passed it is a `FrontendUser` — the type says it, so an administrator's login
 * can never become someone's customer record.
 */
final readonly class CreateCustomer
{
    public function __construct(private CustomerRepositoryInterface $customers) {}

    /**
     * @param  array{name: string, phone?: string|null, address?: string|null, notes?: string|null}  $attributes
     */
    public function handle(array $attributes, ?FrontendUser $login = null): CustomerData
    {
        $customer = DB::transaction(function () use ($attributes, $login): CustomerData {
            $customer = $this->customers->save(new CustomerData(
                id: null,
                name: $attributes['name'],
                phone: $attributes['phone'] ?? null,
                address: $attributes['address'] ?? null,
                notes: $attributes['notes'] ?? null,
            ));

            if ($login === null) {
                return $customer;
            }

            // The repository never writes the link; this step owns it.
            Customer::query()->whereKey($customer->id)->update(['user_id' => $login->getKey()]);

            return $customer->withLogin((int) $login->getKey());
        });

        CustomerAdded::dispatch($customer);

        return $customer;
    }
}
