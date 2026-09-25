<?php

namespace App\Foundation\Modules;

/**
 * Where a module in the codebase stands in this installation.
 */
enum ModuleState: string
{
    /** Listed in `config/modules.php`: its code runs. */
    case Enabled = 'enabled';

    /** Not listed, and something of it is still stored: its data stays. */
    case Disabled = 'disabled';

    /** Not listed, and nothing of it is stored: never installed, or uninstalled. */
    case NotInstalled = 'not installed';

    /**
     * The state as the module commands print it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Enabled => '<fg=green>enabled</>',
            self::Disabled => '<fg=yellow>disabled</> — its data stays',
            self::NotInstalled => '<fg=gray>not installed</>',
        };
    }
}
