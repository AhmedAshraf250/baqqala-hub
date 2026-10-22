<?php

namespace App\Modules\Accounts;

use App\Admin\Contracts\Authorization\ProvidesPermissionsInterface;
use App\Admin\Contracts\Navigation\ProvidesAdminNavigationInterface;
use App\Admin\Contracts\Overlays\ProvidesAdminOverlaysInterface;
use App\Admin\Contracts\Search\ProvidesAdminSearchInterface;
use App\Admin\Contracts\Settings\ProvidesAdminSettingsInterface;
use App\Admin\Contracts\Trash\ProvidesTrashInterface;
use App\Admin\Navigation\NavigationItem;
use App\Admin\Navigation\NavigationSection;
use App\Admin\Overlays\AdminOverlay;
use App\Admin\Settings\SettingsSection;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Modules\Accounts\Areas\Admin\Search\CustomerSearch;
use App\Modules\Accounts\Areas\Admin\Trash\GroupTrash;
use App\Modules\Accounts\Authorization\AccountPermission;
use App\Modules\Accounts\Console\InstallDemoAccountsCommand;
use App\Modules\Accounts\Contracts\AccountLedgerInterface;
use App\Modules\Accounts\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Database\Repositories\AccountRepository;
use App\Modules\Accounts\Domain\Listeners\OpenAccountForNewCustomer;
use App\Modules\Accounts\Domain\Services\AccountLedger;
use App\Modules\Accounts\Domain\Services\AccountSettings;
use App\Modules\Accounts\Domain\Services\ReconcileAccountTotals;
use App\Modules\Customers\Contracts\Events\CustomerAdded;
use Illuminate\Support\Facades\Event;

/**
 * Running accounts: what a customer owes, what they took, and how it was
 * settled.
 *
 * Its shape is a general ledger — *a party owes the institution, entries are
 * posted, and a cached total is kept honest by a row lock* — which is a
 * school's fees and a clinic's bills as much as a shop's tab. Entries are
 * never edited: a mistake is cancelled by a reversal and put right by a
 * replacement. A charge carries its lines, priced from the Catalog module.
 * The party is a customer from the Customers module; both other modules are
 * reached only by id — through their repositories and the `CustomerAdded`
 * event, never a relation.
 *
 * What the owner may change about it — when a debt is late, what a line
 * with no product is called — is the «Accounts» settings tab, declared in
 * {@see AccountSettings} and drawn by the shell.
 */
final class AccountsServiceProvider extends ModuleServiceProvider implements DependsOnModulesInterface, ProvidesAdminNavigationInterface, ProvidesAdminOverlaysInterface, ProvidesAdminSearchInterface, ProvidesAdminSettingsInterface, ProvidesPermissionsInterface, ProvidesTrashInterface
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
     * An account belongs to someone, so the directory has to be present; a
     * charge is priced from the catalog, so it has to be present too.
     *
     * @return list<string>
     */
    public function dependsOn(): array
    {
        return ['customers', 'catalog'];
    }

    /**
     * What this module stores that can fall behind: the summary beside each
     * balance, checked against the ledger by `app:sync`.
     */
    protected function registerModule(): void
    {
        if ($this->app->runningInConsole()) {
            $this->app->tag([ReconcileAccountTotals::class], SyncStepInterface::class);
        }
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
        // The counter's heading, which the shell keeps so the notebook can
        // sit under it too.
        return new NavigationSection('shell.navigation.group.counter', [
            new NavigationItem(
                label: 'accounts::module.navigation.customers',
                route: 'admin.accounts.index',
                icon: 'people',
                // A customer's own page is reached from the list, and sits under it.
                activeWhen: ['admin.accounts.index', 'admin.accounts.show'],
                permission: AccountPermission::View,
            ),
            new NavigationItem(
                label: 'accounts::module.navigation.journal',
                route: 'admin.accounts.journal',
                icon: 'journal-text',
                permission: AccountPermission::View,
            ),
        ]);
    }

    /**
     * The drawer that records «أخذ» and «دفع», on every screen for whoever may
     * post: the customer list and a customer's page open it for their rows,
     * and any other screen with an EntryOpening.
     *
     * @return list<AdminOverlay>
     */
    public function adminOverlays(): array
    {
        return [new AdminOverlay('accounts::admin.entry-drawer', AccountPermission::Post)];
    }

    /**
     * Customers, with where each account stands, in the header's quick search.
     *
     * @return list<class-string<CustomerSearch>>
     */
    public function adminSearchSources(): array
    {
        return [CustomerSearch::class];
    }

    /**
     * Customer groups deleted from the groups dialog, kept to be brought back.
     *
     * @return list<class-string<GroupTrash>>
     */
    public function adminTrashSources(): array
    {
        return [GroupTrash::class];
    }

    /**
     * The values the owner may change, as a tab the shell draws itself.
     *
     * @return list<SettingsSection>
     */
    public function adminSettings(): array
    {
        return [SettingsSection::declared('accounts', 'accounts::module.settings.title', 'accounts::module.settings.description', AccountSettings::definitions(), 'wallet2')];
    }

    public function boot(): void
    {
        // Customers announces; this module reacts. Customers never learns that
        // accounts exist, which is what lets it travel to a product that has
        // no ledger.
        Event::listen(CustomerAdded::class, OpenAccountForNewCustomer::class);

        if ($this->app->runningInConsole()) {
            $this->commands([InstallDemoAccountsCommand::class]);
        }
    }
}
