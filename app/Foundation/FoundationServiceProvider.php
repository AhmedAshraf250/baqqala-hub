<?php

namespace App\Foundation;

use App\Foundation\Area\Area;
use App\Foundation\Console\AboutFoundation;
use App\Foundation\Console\Modules\ListModulesCommand;
use App\Foundation\Console\Modules\ShowModuleCommand;
use App\Foundation\Console\Modules\UninstallModuleCommand;
use App\Foundation\Console\SyncCommand;
use App\Foundation\Contracts\Modules\DependsOnModulesInterface;
use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Foundation\Modules\Sync\SyncCaches;
use App\Foundation\Modules\Sync\SyncMigrations;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Fortify\Fortify;

/**
 * What every product built on this foundation gets, whatever it is about.
 *
 * Registers the modules `config/modules.php` lists — each an ordinary service
 * provider — and the defaults both areas stand on. It never names a module or
 * an area shell; each shell has its own provider.
 */
final class FoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerModules();

        // What the foundation itself stores, for `app:sync`.
        if ($this->app->runningInConsole()) {
            $this->app->tag([SyncMigrations::class, SyncCaches::class], SyncStepInterface::class);
        }
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        if ($this->app->runningInConsole()) {
            $this->registerConsole();
        }
    }

    /**
     * The foundation's console: `app:module:*` for the modules, and its
     * section of `php artisan about`. Each shell registers its own.
     */
    private function registerConsole(): void
    {
        $this->commands([
            ListModulesCommand::class,
            ShowModuleCommand::class,
            SyncCommand::class,
            UninstallModuleCommand::class,
        ]);

        // Here, not with the other production defaults: a web request has no
        // use for it, and naming the class there loaded it on every page.
        UninstallModuleCommand::prohibit($this->app->environment('production'));

        AboutCommand::add('Foundation', AboutFoundation::class);
    }

    /**
     * Register each configured module.
     *
     * Laravel creates and registers each provider itself; the module's own
     * `register()` wires its shape before anything else it does. The registry
     * keeps the instances Laravel returns, and is what the areas ask when they
     * assemble the sidebar, the permissions, and the routes.
     */
    private function registerModules(): void
    {
        $modules = [];

        foreach (config('modules.enabled', []) as $class) {
            $module = $this->app->register($class);

            if (! $module instanceof ModuleServiceProvider) {
                throw new InvalidArgumentException(
                    "[{$class}] is listed in config/modules.php but does not extend ".ModuleServiceProvider::class.'.',
                );
            }

            $modules[] = $module;
        }

        $registry = new ModuleRegistry($modules);

        $this->ensureDependenciesAreInstalled($registry);

        $this->app->instance(ModuleRegistry::class, $registry);
    }

    /**
     * Refuse to start with a module whose dependency is not installed.
     *
     * Enabling Accounts without Customers would otherwise fail much later and
     * far from the cause — on the first account opened. A handful of array
     * lookups per request is what it costs to fail here, by name, instead.
     */
    private function ensureDependenciesAreInstalled(ModuleRegistry $registry): void
    {
        foreach ($registry->all() as $module) {
            if (! $module instanceof DependsOnModulesInterface) {
                continue;
            }

            foreach ($module->dependsOn() as $dependency) {
                if (! $registry->has($dependency)) {
                    throw new InvalidArgumentException(
                        "The [{$module->key()}] module needs [{$dependency}], which config/modules.php does not enable.",
                    );
                }
            }
        }
    }

    /**
     * Defaults for a production-ready application.
     */
    private function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $production = $this->app->environment('production');

        DB::prohibitDestructiveCommands($production);

        Password::defaults(
            static fn (): ?Password => $production
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }

    /**
     * The limits both front doors are throttled by.
     *
     * Named once here because both areas' sign-in routes use them. Each area
     * counts its own failures: the area is part of the key, so someone locked
     * out of one front door is not locked out of the other.
     *
     * Defined when the limiter is first asked for, not at boot: building it
     * builds the cache store, and most requests never throttle anything.
     */
    private function configureRateLimiting(): void
    {
        $this->callAfterResolving(RateLimiter::class, static function (RateLimiter $limiter): void {
            $limiter->for('login', static function (Request $request): Limit {
                $throttleKey = Area::fromRequest($request)->value.'|'.Str::transliterate(
                    Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip(),
                );

                return Limit::perMinute(5)->by($throttleKey);
            });

            $limiter->for('two-factor', static function (Request $request): Limit {
                return Limit::perMinute(5)->by((string) $request->session()->get('login.id'));
            });

            $limiter->for('passkeys', static function (Request $request): Limit {
                $credentialId = $request->input('credential.id');

                return Limit::perMinute(10)->by(
                    ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
                );
            });
        });
    }
}
