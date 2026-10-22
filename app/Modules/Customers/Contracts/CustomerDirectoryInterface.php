<?php

namespace App\Modules\Customers\Contracts;

use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Events\CustomerAdded;
use App\Modules\Customers\Contracts\Exceptions\PhoneAlreadyUsed;

/**
 * Who is on the shop's books: adding someone, and changing what is known
 * about them.
 *
 * A service, not the repository, because each step has a rule — one phone
 * number is one person — and adding someone is announced. A login is never
 * given here: the counter adds a name and a number, and portal access is
 * granted on its own, later, if at all.
 */
interface CustomerDirectoryInterface
{
    /**
     * Add a customer, with the phone stored as its digits, and announce
     * {@see CustomerAdded}.
     *
     * @throws PhoneAlreadyUsed
     */
    public function add(CustomerData $customer): CustomerData;

    /**
     * Change a stored customer's name, phone, address, or notes. Their login,
     * if they have one, stays as it is.
     *
     * @throws PhoneAlreadyUsed
     */
    public function update(CustomerData $customer): CustomerData;
}
