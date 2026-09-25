<?php

namespace App\Modules\Customers;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Contracts\Modules\UninstallableInterface;
use App\Foundation\Identity\Models\FrontendUser;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Customers\Authorization\CustomerPermission;
use App\Modules\Customers\Console\GrantPortalAccessCommand;
use App\Modules\Customers\Contracts\CustomerRepositoryInterface;
use App\Modules\Customers\Database\Models\Customer;
use App\Modules\Customers\Database\Repositories\CustomerRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The people the shop does business with.
 *
 * Their identity starts here and never outside: someone is added to the books
 * at the counter, and given a portal login later only if the shop decides to.
 *
 * TODO(customers): the directory, and the fast Arabic-aware name lookup
 * TODO(customers): granting portal access from a screen rather than the console
 */
final class CustomersServiceProvider extends ModuleServiceProvider implements ProvidesAdminNavigationInterface, ProvidesPermissionsInterface, UninstallableInterface
{
    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    /**
     * What other modules may ask the container for. Each implementation is
     * `#[Scoped]`: one instance per request.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        CustomerRepositoryInterface::class => CustomerRepository::class,
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

    public function adminNavigation(): NavigationSection
    {
        return new NavigationSection($this->name(), [
            new NavigationItem(
                label: 'customers::module.navigation.customers',
                route: 'admin.customers.index',
                icon: 'people',
                permission: CustomerPermission::View,
            ),
        ]);
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
