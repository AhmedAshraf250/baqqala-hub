<?php

namespace Tests\Fixtures\Modules\Probe;

use App\Foundation\Contracts\Modules\UninstallableInterface;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A module that exists only to be registered by a test, with a view of each
 * kind a real module will carry, a table of its own, and files it keeps
 * outside that table — the case `UninstallableInterface` is for.
 */
final class ProbeServiceProvider extends ModuleServiceProvider implements UninstallableInterface
{
    /**
     * Whether its table could still be read when its own cleanup ran.
     */
    public static ?bool $tableExistedDuringUninstall = null;

    /** A module whose own cleanup is broken: it forgets its files. */
    public static bool $forgetsItsFiles = false;

    public function key(): string
    {
        return 'probe';
    }

    public function leftovers(): array
    {
        return array_map(
            static fn (string $file): string => "stored file {$file}",
            Storage::disk('local')->files('probe'),
        );
    }

    public function uninstall(): void
    {
        self::$tableExistedDuringUninstall = Schema::hasTable('probe_items');

        if (! self::$forgetsItsFiles) {
            Storage::disk('local')->deleteDirectory('probe');
        }
    }
}
