<?php

namespace App\Modules\Accounts;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Database\Repositories\AccountRepository;
use App\Modules\Accounts\Domain\Listeners\OpenAccountForNewCustomer;
use App\Modules\Accounts\Domain\Services\AccountLedger;
use App\Modules\Customers\Contracts\Events\CustomerAdded;
use Illuminate\Support\Facades\Event;

/**
 * Running accounts: what a customer owes, and how it was settled.
 *
 * Its shape is a general ledger — *a party owes the institution, entries are
 * posted, and a cached total is kept honest by a row lock* — which is a
 * school's fees and a clinic's bills as much as a shop's tab. Today the party
 * is a customer from the Customers module, reached only by id — through that
 * module's repository and its `CustomerAdded` event, never a relation.
 *
 * TODO(accounts): the statement screen, and the quick entry form at the counter
 * TODO(accounts): reversal from the UI, and a reconciliation command
 */
final class AccountsServiceProvider extends ModuleServiceProvider implements DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesPermissionsInterface
{
    /** Its defaults, as config('accounts.…'). */
    protected ?string $config = 'Config/accounts.php';

    /** Its screens, by area. */
    protected array $routes = ['admin' => 'Areas/Admin/routes.php'];

    /**
     * What other modules may ask the container for. Each implementation is
     * `#[Scoped]`: one instance per request.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AccountRepositoryInterface::class => AccountRepository::class,
        AccountLedgerInterface::class => AccountLedger::class,
    ];

    public function key(): string
    {
        return 'accounts';
    }

    /**
     * An account belongs to someone, so the directory has to be present.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return ['customers'];
    }

    /**
     * @return list<AccountPermission>
     */
    public function permissions(): array
    {
        return AccountPermission::cases();
    }

    public function adminNavigation(): NavigationSection
    {
        return new NavigationSection($this->name(), [
            new NavigationItem(
                label: 'accounts::module.navigation.accounts',
                route: 'admin.accounts.index',
                icon: 'journal-text',
                permission: AccountPermission::View,
            ),
        ]);
    }

    public function boot(): void
    {
        // Customers announces; this module reacts. Customers never learns that
        // accounts exist, which is what lets it travel to a product that has
        // no ledger.
        Event::listen(CustomerAdded::class, OpenAccountForNewCustomer::class);
    }
}
