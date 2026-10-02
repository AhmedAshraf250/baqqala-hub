<?php

namespace App\Foundation\Console;

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\User;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Foundation\Modules\ModuleState;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The foundation's section of `php artisan about`: the version, the languages
 * and money, each area, and the modules at a glance.
 *
 *     php artisan about --only=foundation
 *
 * Laravel's own command, so the product's state sits beside the framework's —
 * drivers, caches, environment — in the one place a developer already looks.
 */
final readonly class AboutFoundation
{
    public function __construct(
        private Config $config,
        private ModuleInspector $modules,
    ) {}

    /**
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        return [
            'Version' => (string) $this->config->get('foundation.version'),
            'Languages' => $this->languages(),
            'Currency' => sprintf('%s, %d decimal places', $this->config->get('foundation.currency.code'), $this->config->get('foundation.currency.precision')),
            'OPcache' => $this->opcache(),
            ...$this->areas(),
            ...$this->modulesAtAGlance(),
        ];
    }

    /**
     * Whether web requests run with PHP's compiled-code cache. php.ini belongs
     * to the machine, not the project, so a new machine starts without it —
     * and every page then recompiles some nine hundred files (~0.5 s → ~0.15 s
     * measured). Asked from the console, so it reports the web setting
     * (`opcache.enable`), not the console's own (`opcache.enable_cli`).
     */
    private function opcache(): string
    {
        if (! extension_loaded('Zend OPcache')) {
            return '<fg=yellow;options=bold>OFF</> — php.ini has `zend_extension=opcache` commented out (see README)';
        }

        return filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)
            ? 'on for web requests'
            : '<fg=yellow;options=bold>OFF</> — set `opcache.enable=1` in php.ini (see README)';
    }

    private function languages(): string
    {
        $default = (string) $this->config->get('foundation.locales.default');
        $supported = array_keys((array) $this->config->get('foundation.locales.supported', []));

        return implode(', ', array_map(
            static fn (string $locale): string => $locale === $default ? "{$locale} (default)" : $locale,
            $supported,
        )).' · fallback '.$this->config->get('foundation.locales.fallback');
    }

    /**
     * @return array<string, string>
     */
    private function areas(): array
    {
        $logins = Schema::hasTable('users')
            ? User::query()->toBase()->selectRaw('area, count(*) as total')->groupBy('area')->pluck('total', 'area')->all()
            : [];

        $areas = [];

        foreach (Area::cases() as $area) {
            $count = (int) ($logins[$area->value] ?? 0);

            $areas["Area {$area->value}"] = sprintf(
                'guard %s · cookie %s · %d %s',
                $area->guard(),
                $area->sessionCookie(),
                $count,
                Str::plural('login', $count),
            );
        }

        return $areas;
    }

    /**
     * @return array<string, string>
     */
    private function modulesAtAGlance(): array
    {
        $byState = [];
        $pending = 0;

        foreach ($this->modules->all() as $module) {
            $state = $this->modules->state($module);
            $byState[$state->value][] = $module;

            // `migrate` runs only what is enabled; a disabled module's are not
            // waiting for anything.
            if ($state === ModuleState::Enabled) {
                $pending += count($this->modules->pendingMigrations($module));
            }
        }

        $keys = static fn (ModuleState $state): string => implode(', ', array_map(
            static fn (ModuleServiceProvider $module): string => $module->key(),
            $byState[$state->value] ?? [],
        )) ?: 'none';

        return [
            'Modules enabled' => $keys(ModuleState::Enabled),
            'Modules disabled, data kept' => $keys(ModuleState::Disabled),
            'Module migrations pending' => $pending === 0 ? 'none' : "<fg=yellow>{$pending}</> — run php artisan migrate",
        ];
    }
}
