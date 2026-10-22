<?php

namespace App\Modules\Customers;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Foundation\Contracts\Modules\UninstallableInterface;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Customers\Console\GrantPortalAccessCommand;
use App\Modules\Customers\Contracts\CustomerDirectoryInterface;
use App\Modules\Customers\Contracts\CustomerGroupRepositoryInterface;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Contracts\Enums\CustomerPermission;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Repositories\CustomerGroupRepository;
use App\Modules\Customers\Database\Repositories\CustomerRepository;
use App\Modules\Customers\Domain\Services\CustomerDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The people the shop does business with.
 *
 * Their identity starts here and never outside: someone is added to the books
 * at the counter, and given a portal login later only if the shop decides to.
 * It owns who they are, the groups the owner puts them in, and finding them
 * by any spelling of their name or a few digits of their phone — and no
 * screen: the counter works with customers in Accounts, which reads them
 * through these contracts.
 *
 * TODO(customers): granting portal access from Accounts' customer page rather than the console
 */
final class CustomersServiceProvider extends ModuleServiceProvider implements ProvidesPermissionsInterface, UninstallableInterface
{
    /**
     * What other modules may ask the container for. Each implementation is
     * `#[Scoped]`: one instance per request.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CustomerRepositoryInterface::class => CustomerRepository::class,
        CustomerGroupRepositoryInterface::class => CustomerGroupRepository::class,
        CustomerDirectoryInterface::class => CustomerDirectory::class,
    ];

    public function key(): string
    {
        return 'customers';
    }

    /**
     * @return list<CustomerPermission>
     */
    public function permissions(): array
    {
        return CustomerPermission::cases();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([GrantPortalAccessCommand::class]);
        }
    }

    /**
     * The portal logins this module handed out.
     *
     * They are rows of `users`, which the foundation owns, so no migration of
     * this module removes them — and left behind, they would still open the
     * portal for people the shop no longer keeps a record of.
     */
    public function leftovers(): array
    {
        $logins = $this->portalLogins()->count();

        return $logins === 0 ? [] : [sprintf('%d portal %s given to customers', $logins, Str::plural('login', $logins))];
    }

    /**
     * Their passkeys go with them, by the foreign key; a session left open by
     * one of them no longer resolves to anyone.
     */
    public function uninstall(): void
    {
        $this->portalLogins()->delete();
    }

    /**
     * @return Builder<FrontendUser>
     */
    private function portalLogins(): Builder
    {
        return FrontendUser::query()->whereIn('id', Customer::query()->whereNotNull('user_id')->select('user_id'));
    }
}
