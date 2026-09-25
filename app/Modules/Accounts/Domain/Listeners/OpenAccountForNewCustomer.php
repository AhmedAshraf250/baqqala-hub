<?php

namespace App\Modules\Accounts\Domain\Listeners;

use App\Modules\Accounts\Domain\Actions\OpenAccount;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Contracts\Events\CustomerAdded;

/**
 * Opens a running account the moment someone joins the books.
 *
 * Listening rather than being called keeps the arrow one-way — Accounts knows
 * Customers, Customers does not know Accounts. What arrives is a
 * {@see CustomerData}, so this cannot reach back into the record it describes.
 */
final readonly class OpenAccountForNewCustomer
{
    public function __construct(private OpenAccount $openAccount) {}

    public function handle(CustomerAdded $event): void
    {
        $this->openAccount->handle($event->customer->id);
    }
}
