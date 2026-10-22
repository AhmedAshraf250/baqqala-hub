<?php

namespace App\Modules\Accounts\Console;

use App\Foundation\Identity\Models\AdminUser;
use App\Modules\Accounts\Database\Seeders\DemoAccounts;
use Illuminate\Console\Command;

/**
 * Fills a developer's machine with made-up customers and three months of
 * their accounts, posted through the real ledger — to try the screens on,
 * never for a real shop.
 *
 * Refuses in production, refuses when the demo is already there, and needs
 * an administrator to have recorded it all.
 */
class InstallDemoAccountsCommand extends Command
{
    protected $signature = 'app:accounts:demo';

    protected $description = 'Add made-up customers with three months of entries, for trying the screens (never in production)';

    public function handle(DemoAccounts $demo): int
    {
        if ($this->laravel->isProduction()) {
            $this->components->error('The demo customers are made up: they are never added to a shop in production.');

            return self::FAILURE;
        }

        if ($demo->isInstalled()) {
            $this->components->warn('The demo customers are already on the books; nothing was added.');

            return self::SUCCESS;
        }

        $administrator = AdminUser::query()->orderBy('id')->first();

        if ($administrator === null) {
            $this->components->error('Someone has to have recorded the entries: make an administrator first, with app:admin:grant.');

            return self::FAILURE;
        }

        $result = $demo->install((int) $administrator->getKey());

        $this->components->info(sprintf(
            'Added %d customers in %d groups, with %d entries over three months, recorded as %s.',
            $result['customers'],
            $result['groups'],
            $result['entries'],
            $administrator->email,
        ));

        return self::SUCCESS;
    }
}
