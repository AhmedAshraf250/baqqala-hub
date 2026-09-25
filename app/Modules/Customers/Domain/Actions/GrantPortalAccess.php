<?php

namespace App\Modules\Customers\Domain\Actions;

use App\Foundation\Identity\Models\FrontendUser;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Domain\Exceptions\CustomerAlreadyHasLogin;
use Illuminate\Support\Facades\DB;

/**
 * Gives an existing customer the ability to sign in and watch their own tab.
 *
 * The only way a customer gets a login. The person is already in the shop's
 * books — bought on credit for months, perhaps — and is now being handed one.
 * Their record and their ledger are untouched, so their history follows them
 * into the portal.
 *
 * Safe when two people grant access to the same customer at once. The check
 * and the link happen inside one transaction on a locked row, and the link
 * itself only lands if the customer is still unlinked — so the second grant
 * is refused, and the login it created is rolled back with it rather than
 * left behind with no customer. The condition matters on databases that do
 * not honour row locks (SQLite); on the others the lock alone would do.
 *
 * Works on the module's own model directly: this is a local, locked update,
 * and routing it through the repository's find-then-save is exactly what let
 * the race in.
 */
final class GrantPortalAccess
{
    public function handle(int $customerId, string $email, string $password): FrontendUser
    {
        return DB::transaction(function () use ($customerId, $email, $password): FrontendUser {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);

            if ($customer->user_id !== null) {
                throw new CustomerAlreadyHasLogin($customer->toData());
            }

            // Created through the area's own model, which is what sets its
            // area: nothing here, and nothing a request can reach, names it.
            $login = FrontendUser::create([
                'name' => $customer->name,
                'email' => $email,
                'password' => $password,
            ]);

            $linked = Customer::query()
                ->whereKey($customerId)
                ->whereNull('user_id')
                ->update(['user_id' => $login->getKey()]);

            if ($linked !== 1) {
                throw new CustomerAlreadyHasLogin($customer->refresh()->toData());
            }

            return $login;
        });
    }
}
