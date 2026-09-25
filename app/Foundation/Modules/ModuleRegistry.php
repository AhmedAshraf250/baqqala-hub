<?php

namespace App\Foundation\Modules;

use Illuminate\Support\Collection;

/**
 * Every module this installation runs, in the order `config/modules.php` lists
 * them.
 *
 * The one place that knows the list. The areas ask it which modules offer a
 * given capability rather than naming any of them, which is what keeps adding
 * a module to one line of configuration.
 */
final class ModuleRegistry
{
    /**
     * @var Collection<string, ModuleServiceProvider>
     */
    private Collection $modules;

    /**
     * @param  list<ModuleServiceProvider>  $modules
     */
    public function __construct(array $modules)
    {
        $this->modules = collect($modules)->keyBy(
            static fn (ModuleServiceProvider $module): string => $module->key(),
        );
    }

    /**
     * @return Collection<string, ModuleServiceProvider>
     */
    public function all(): Collection
    {
        return $this->modules;
    }

    /**
     * The modules offering a given capability, in registration order.
     *
     * @template TCapability of object
     *
     * @param  class-string<TCapability>  $capability
     * @return Collection<string, ModuleServiceProvider&TCapability>
     */
    public function providing(string $capability): Collection
    {
        /** @var Collection<string, ModuleServiceProvider&TCapability> */
        return $this->modules->filter(
            static fn (ModuleServiceProvider $module): bool => $module instanceof $capability,
        );
    }

    /**
     * A module by its key, or null if this installation does not run it.
     */
    public function find(string $key): ?ModuleServiceProvider
    {
        return $this->modules->get($key);
    }

    /**
     * Whether this installation runs the given module.
     */
    public function has(string $key): bool
    {
        return $this->modules->has($key);
    }
}
