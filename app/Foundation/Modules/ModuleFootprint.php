<?php

namespace App\Foundation\Modules;

/**
 * What of a module is stored: its tables, the migrations that made them, what
 * the shells hold for it, and whatever it keeps elsewhere.
 *
 * `app:module:show` reports it, uninstalling removes it, and after an
 * uninstall it must be empty.
 */
final readonly class ModuleFootprint
{
    /**
     * @param  array<string, int>  $tables  Each table its migrations created, with its row count.
     * @param  list<string>  $migrations  Its migrations that have run, newest first.
     * @param  list<string>  $held  What the shells hold for it — its permissions.
     * @param  list<string>  $leftovers  What the module says it keeps outside its tables.
     */
    public function __construct(
        public array $tables,
        public array $migrations,
        public array $held,
        public array $leftovers,
    ) {}

    /**
     * Whether nothing of the module is stored anywhere.
     */
    public function isEmpty(): bool
    {
        return $this->migrations === [] && $this->held === [] && $this->leftovers === [];
    }
}
