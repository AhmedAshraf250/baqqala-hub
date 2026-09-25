<?php

namespace App\Foundation\Modules;

use App\Foundation\Area\Area;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * A unit of the product, as an ordinary Laravel service provider.
 *
 * A module answers "what is this system about" — a catalog, a ledger, a
 * directory of people. It binds its contracts, listens for events, and offers
 * console commands the way any Laravel package does: `$bindings`,
 * `Event::listen()` in `boot()`, `$this->commands()`.
 *
 * The provider is the one place that says what the module brings. What might
 * or might not exist is declared, not searched for:
 *
 *     $config   its defaults, merged as `config('{key}.…')`
 *     $routes   its screens in each area: ['admin' => …, 'frontend' => …]
 *
 * and what always has the same shape is wired by convention, as paths only:
 *
 *     Resources/views/{area}/   views, components, and Livewire screens,
 *                               as `{key}::admin.…` / `{key}::frontend.…`
 *     View/Components/{Area}/   the class behind a component that needs data
 *     Resources/lang/{locale}/  strings, as `{key}::…`
 *     Database/Migrations/      its tables
 *
 * Nothing here touches the disk on a request. No file is checked for: a
 * declared file is expected to exist, and a test fails the build if it does
 * not. Views, strings, and migrations are registered as paths and opened only
 * when something asks for them, and the config file is skipped by Laravel
 * itself once configuration is cached.
 *
 * What a module adds to an area's chrome — a sidebar section, permissions, a
 * settings tab — it declares through that area's capability interfaces.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The module's defaults, relative to its folder: `Config/{key}.php`.
     */
    protected ?string $config = null;

    /**
     * The module's route files, relative to its folder, keyed by area.
     *
     * @var array<string, string>
     */
    protected array $routes = [];

    private ?string $root = null;

    /**
     * A stable identifier: the view, translation, and config namespace, and the
     * URL and route-name segment its screens live under.
     *
     * Lowercase, and never renamed — renaming it is a data migration.
     */
    abstract public function key(): string;

    /**
     * The module's human name, as a translation key.
     */
    public function name(): string
    {
        return $this->key().'::module.name';
    }

    /**
     * An absolute path inside this module.
     */
    final public function path(string $relative = ''): string
    {
        $this->root ??= dirname((string) (new ReflectionClass($this))->getFileName());

        return $relative === '' ? $this->root : $this->root.'/'.$relative;
    }

    /**
     * Laravel calls this. Final, so the module's shape is wired whatever the
     * module itself does: a module that overrode `register()` and forgot
     * `parent::register()` would lose its views, strings, and config without a
     * word. A module registers its own things in {@see registerModule()}.
     */
    final public function register(): void
    {
        $this->registerShape();
        $this->registerModule();
    }

    /**
     * The module's own registrations, beyond what `$bindings` declares.
     */
    protected function registerModule(): void
    {
        //
    }

    /**
     * The route file this module declares for an area, or null if it has no
     * screens there.
     */
    final public function routesFor(Area $area): ?string
    {
        $file = $this->routes[$area->value] ?? null;

        return $file === null ? null : $this->path($file);
    }

    /**
     * The config file this module declares, or null.
     */
    final public function configFile(): ?string
    {
        return $this->config === null ? null : $this->path($this->config);
    }

    /**
     * A module's views are registered the way the areas' own are in
     * `config/livewire.php`, in all three places a name is looked up: as
     * views, as Blade components (`<x-{key}::admin.badge>`), and as Livewire
     * components (`Route::livewire(…, '{key}::admin.statement')`).
     *
     * A component is its template alone until it needs data; then it gets a
     * class in `View/Components/`, mirroring the view's path —
     * `<x-{key}::admin.balance-card>` is `View\Components\Admin\BalanceCard` —
     * the way the shells' are in `app/{Admin,Frontend}/View/Components/`.
     *
     * Each is a path added to a list, and nothing is opened until a page asks
     * for it. They are added to the finders directly rather than through
     * `loadViewsFrom()`, which checks a `vendor/` override directory per
     * module on every request.
     */
    private function registerShape(): void
    {
        $key = $this->key();
        $config = $this->configFile();

        if ($config !== null) {
            $this->mergeConfigFrom($config, $key);
        }

        $views = $this->path('Resources/views');
        $components = Str::beforeLast(static::class, '\\').'\\View\\Components';

        $this->callAfterResolving('view', static fn ($factory) => $factory->addNamespace($key, $views));
        $this->callAfterResolving('blade.compiler', static function ($blade) use ($views, $components, $key): void {
            $blade->anonymousComponentPath($views, $key);
            $blade->componentNamespace($components, $key);
        });
        $this->callAfterResolving('livewire.finder', static fn ($finder) => $finder->addNamespace($key, viewPath: $views));

        $this->loadTranslationsFrom($this->path('Resources/lang'), $key);
        $this->loadMigrationsFrom($this->path('Database/Migrations'));
    }
}
