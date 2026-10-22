<?php

namespace App\Foundation\Modules\Sync;

use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;

/**
 * Caches built from older code.
 *
 * Laravel's caches (`php artisan optimize`) are what keep a request cheap as
 * modules multiply: routes and configuration are read once into a file. The
 * price is that a cache does not notice the code changing. Every cache holds
 * every module, so a report on one module shows them too.
 */
final readonly class SyncCaches implements SyncStepInterface
{
    /**
     * The Artisan command that rebuilds each cache.
     */
    private const array Rebuild = [
        'config' => 'config:cache',
        'routes' => 'route:cache',
        'views' => 'view:clear',
    ];

    public function __construct(
        private Application $app,
        private Config $config,
        private Artisan $artisan,
    ) {}

    public function title(): string
    {
        return 'Caches';
    }

    public function findings(?ModuleServiceProvider $module = null): array
    {
        return array_values(array_filter([
            $this->cached('config', $this->app->configurationIsCached(), $this->app->getCachedConfigPath(), [
                'config/*.php', 'app/Modules/*/Config/*.php',
            ]),
            $this->cached('routes', $this->app->routesAreCached(), $this->app->getCachedRoutesPath(), [
                'routes/*.php', 'app/Modules/*/Areas/*/routes.php',
            ]),
            $this->compiledViews(),
        ]));
    }

    /**
     * Rebuild each cache that is out of sync, with Laravel's own command.
     *
     * `view:clear` reports success even when the operating system refused to
     * delete a file — on Windows, files an administrator's terminal compiled
     * cannot be deleted from an ordinary one. So the compiled views are looked
     * at again afterwards, and a refusal is said out loud.
     */
    public function fix(?ModuleServiceProvider $module = null): void
    {
        foreach ($this->findings() as [$cache]) {
            $this->artisan->call(self::Rebuild[$cache]);
        }

        $left = $this->olderCompiledViews();

        if ($left !== []) {
            throw new RuntimeException(sprintf(
                'Could not delete %d compiled %s in %s: the operating system refused. On Windows this happens when an administrator\'s terminal compiled them — delete them from an administrator terminal, or give your account Modify rights on the project folder, then run this again.',
                count($left),
                Str::plural('view', count($left)),
                (string) $this->config->get('view.compiled'),
            ));
        }
    }

    /**
     * @param  list<string>  $sources  Globs, from the project root.
     * @return array{0: string, 1: string}|null
     */
    private function cached(string $name, bool $isCached, string $cache, array $sources): ?array
    {
        if (! $isCached) {
            return $this->app->isProduction()
                ? [$name, 'not cached, on a production server']
                : null;
        }

        $newest = max([0, ...array_map(
            static fn (string $file): int => (int) filemtime($file),
            array_merge(...array_map(static fn (string $glob): array => glob(base_path($glob)) ?: [], $sources)),
        )]);

        return filemtime($cache) < $newest
            ? [$name, 'cached before the latest change to its files']
            : null;
    }

    /**
     * A compiled view that uses `<x-admin::…>` remembers whether the tag had a
     * class when it was compiled. Adding or removing a component class leaves
     * those views pointing at the old answer until they are compiled again.
     *
     * @return array{0: string, 1: string}|null
     */
    private function compiledViews(): ?array
    {
        $older = $this->olderCompiledViews();

        return $older === []
            ? null
            : ['views', count($older).' compiled before a component class last changed, and may use the old answer'];
    }

    /**
     * @return list<SplFileInfo>
     */
    private function olderCompiledViews(): array
    {
        $compiled = (string) $this->config->get('view.compiled');

        $newestClass = max([0, ...array_map(
            static fn (SplFileInfo $file): int => $file->getMTime(),
            array_merge(...array_map(
                static fn (string $directory): array => File::allFiles($directory),
                glob(app_path('{*,Modules/*}/View/Components'), GLOB_BRACE | GLOB_ONLYDIR) ?: [],
            )),
        )]);

        // Read afresh: the fix asks again right after deleting.
        clearstatcache();

        return array_values(array_filter(
            is_dir($compiled) ? File::files($compiled) : [],
            static fn (SplFileInfo $file): bool => $file->getMTime() < $newestClass,
        ));
    }
}
