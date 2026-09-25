<?php

namespace App\Modules\Customers\Console;

use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Data\CustomerData;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Domain\Actions\GrantPortalAccess;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;

/**
 * Hands an existing customer a login for the customer portal.
 *
 * There is no public sign-up: a customer is someone the shop already deals
 * with, so their identity starts in the books and access is granted from here
 * or from inside the admin area. Their ledger follows them into the portal
 * untouched.
 */
class GrantPortalAccessCommand extends Command
{
    protected $signature = 'app:customers:grant-portal-access
                            {customer? : The id of the customer to give a login}
                            {--email= : The email address they will sign in with}';

    protected $description = 'Give an existing customer access to the customer portal';

    public function handle(GrantPortalAccess $grantAccess, CustomerRepositoryInterface $customers): int
    {
        $customer = $this->resolveCustomer($customers);

        if ($customer === null) {
            $this->components->error('No customer found.');

            return self::FAILURE;
        }

        if ($customer->hasPortalAccess()) {
            $this->components->info("[{$customer->name}] already has a login.");

            return self::SUCCESS;
        }

        $email = $this->emailArgument() ?? text(
            label: "Which email address will {$customer->name} sign in with?",
            required: true,
        );

        $password = (string) password(label: 'Choose a password for them', required: true);

        $grantAccess->handle((int) $customer->id, $email, $password);

        $this->components->info("[{$customer->name}] can now sign in at the customer portal.");

        return self::SUCCESS;
    }

    /**
     * The email address given on the command line, if there was one.
     */
    private function emailArgument(): ?string
    {
        $value = $this->option('email');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Find the customer to act on, by id or by searching on name.
     */
    private function resolveCustomer(CustomerRepositoryInterface $customers): ?CustomerData
    {
        $id = $this->argument('customer');

        if ($id !== null) {
            return $customers->find((int) $id);
        }

        $chosen = search(
            label: 'Which customer?',
            options: fn (string $value) => Customer::query()
                ->whereNull('user_id')
                ->when($value !== '', fn ($query) => $query->where('name', 'like', "%{$value}%"))
                ->orderBy('name')
                ->limit(config('admin.search.max_results_per_group'))
                ->pluck('name', 'id')
                ->all(),
        );

        return $customers->find((int) $chosen);
    }
}
