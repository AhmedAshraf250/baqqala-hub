<?php

namespace App\Foundation\Console\Modules;

use App\Foundation\Modules\ModuleFootprint;
use Illuminate\Console\Command;

/**
 * How the module commands print what of a module is stored.
 *
 * @mixin Command
 */
trait WritesFootprint
{
    private function writeFootprint(ModuleFootprint $footprint): void
    {
        foreach ($footprint->tables as $table => $rows) {
            $this->components->twoColumnDetail("table {$table}", "{$rows} rows");
        }

        foreach ($footprint->migrations as $migration) {
            $this->components->twoColumnDetail("migration {$migration}", 'ran');
        }

        foreach ([...$footprint->held, ...$footprint->leftovers] as $line) {
            $this->components->twoColumnDetail($line);
        }
    }

    /**
     * A path as the developer would type it, from the project root.
     */
    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
