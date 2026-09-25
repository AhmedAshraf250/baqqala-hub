<?php

namespace App\Admin;

use App\Admin\Authorization\RemoveModulePermissions;
use App\Admin\Authorization\RoleDefinitions;
use App\Admin\Authorization\SyncPermissions;
use App\Admin\Authorization\SyncRoleGrants;
use App\Admin\Console\AboutAdminArea;
use App\Admin\Console\GrantAdminAccessCommand;
use App\Admin\Http\Middleware\EnsureUserIsAdmin;
use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Contracts\Modules\UninstallStepInterface;
use App\Foundation\Identity\Models\AdminUser;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * The admin area's wiring: its view components, its authorization, its
 * middleware's reach into Livewire, and its console.
 *
 * Its registries are not registered here: each declares `#[Scoped]` on itself,
 * so how long an object lives is stated where the object is defined.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The admin shell stores each module's permissions and the roles that
        // hold them: it checks they match the code, and removes a module's
        // when the module is uninstalled. Both happen only in the console.
        if ($this->app->runningInConsole()) {
            $this->app->tag([SyncPermissions::class, SyncRoleGrants::class], SyncStepInterface::class);
            $this->app->tag([RemoveModulePermissions::class], UninstallStepInterface::class);
        }
    }

    public function boot(): void
    {
        // `<x-admin::layout.sidebar>` is the class App\Admin\View\Components\Layout\Sidebar
        // when there is one, and the template alone when there is not.
        Blade::componentNamespace('App\\Admin\\View\\Components', 'admin');

        $this->authorizeUnrestrictedRole();
        $this->persistMiddlewareThroughLivewire();

        if ($this->app->runningInConsole()) {
            $this->commands([GrantAdminAccessCommand::class]);

            AboutCommand::add('Admin area', AboutAdminArea::class);
        }
    }

    /**
     * The unrestricted role short-circuits every check.
     *
     * Returning null — not false — for everyone else is what keeps this a
     * shortcut rather than a verdict: the normal permission check still runs.
     */
    private function authorizeUnrestrictedRole(): void
    {
        Gate::before(function (mixed $user): ?bool {
            return $user instanceof AdminUser
                && $user->hasRole($this->app->make(RoleDefinitions::class)->unrestricted())
                ? true
                : null;
        });
    }

    /**
     * Run the area's own middleware on every Livewire update, too.
     *
     * Livewire sends each component action to one endpoint and re-runs only
     * the middleware it is told to. Laravel's guard check is on that list by
     * default; the area check and the password confirmation are not, so
     * without this an expired confirmation kept a sensitive screen working.
     */
    private function persistMiddlewareThroughLivewire(): void
    {
        Livewire::addPersistentMiddleware([
            EnsureUserIsAdmin::class,
            RequireAdminPasswordConfirmation::class,
        ]);
    }
}
