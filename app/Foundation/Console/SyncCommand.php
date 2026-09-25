<?php

namespace App\Foundation\Console;

use App\Foundation\Contracts\Modules\SyncStepInterface;
use App\Foundation\Modules\ModuleInspector;
use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

/**
 * Brings what is stored in line with the code, after a module changed.
 *
 *     php artisan app:sync             every running module
 *     php artisan app:sync customers   one of them
 *
 * It shows first — migrations not run, permissions and role grants not
 * stored, caches built from older code — and changes nothing until it is told
 * to: it asks, and a "no", or no answer at all, leaves everything as it was.
 * `--force` skips the question, for a deploy script. Each layer that stores
 * something adds its own step (see {@see SyncStepInterface}); this command
 * only asks them.
 */
final class SyncCommand extends Command
{
    protected $signature = 'app:sync
                            {module? : Only this module}
                            {--force : Fix without asking}';

    protected $description = 'Show what is stored behind the code — migrations, permissions, role grants, caches — and fix it once approved';

    public function handle(ModuleInspector $inspector): int
    {
        $module = null;
        $key = $this->argument('module');

        if (is_string($key) && $key !== '') {
            $module = $inspector->find($key);

            if ($module === null || ! $inspector->isEnabled($module)) {
                $this->components->error("[{$key}] is not a running module. See app:module:list.");

                return self::FAILURE;
            }
        }

        $outOfSync = $this->report($module);

        if ($outOfSync === 0) {
            $this->components->info('Everything stored matches the code.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm("Fix these {$outOfSync} now?", default: false)) {
            $this->components->warn('Nothing was changed.');

            return self::SUCCESS;
        }

        // In order, each step looking again just before it fixes: running the
        // migrations can create the tables the next step stores into.
        foreach ($this->steps() as $step) {
            if ($step->findings($module) !== []) {
                $this->components->task($step->title(), fn () => $step->fix($module));
            }
        }

        $this->newLine();

        if ($this->report($module) > 0) {
            $this->components->error('Some of it is still out of sync.');

            return self::FAILURE;
        }

        $this->components->info('Everything stored now matches the code.');

        return self::SUCCESS;
    }

    /**
     * Print what every step finds, and say how much that is.
     */
    private function report(?ModuleServiceProvider $module): int
    {
        $total = 0;

        foreach ($this->steps() as $step) {
            $findings = $step->findings($module);
            $total += count($findings);

            $this->components->twoColumnDetail(
                "<options=bold>{$step->title()}</>",
                $findings === [] ? '<fg=green>in sync</>' : '<fg=yellow>'.count($findings).' out of sync</>',
            );

            foreach ($findings as [$what, $wrong]) {
                $this->components->twoColumnDetail("  {$what}", $wrong);
            }
        }

        $this->newLine();

        return $total;
    }

    /**
     * @return list<SyncStepInterface>
     */
    private function steps(): array
    {
        $steps = [];

        foreach ($this->laravel->tagged(SyncStepInterface::class) as $step) {
            if ($step instanceof SyncStepInterface) {
                $steps[] = $step;
            }
        }

        return $steps;
    }
}
