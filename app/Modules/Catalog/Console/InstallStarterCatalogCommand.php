<?php

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Domain\Actions\InstallStarterCatalog;
use Illuminate\Console\Command;

/**
 * Gives a new shop a catalog to start from: a general grocery's shelves and
 * products, from `Database/Seeders/starter-catalog.php`.
 *
 * Only adds — what is already in the catalog, edited or not, is left alone —
 * so it is safe on a shop that already sells, and safe to run twice.
 */
class InstallStarterCatalogCommand extends Command
{
    protected $signature = 'app:catalog:install-starter';

    protected $description = 'Add the starter grocery catalog — only the categories and products not there yet';

    public function handle(InstallStarterCatalog $install): int
    {
        $result = $install->handle();

        $this->components->info(sprintf(
            'Added %d categories and %d products. %d products were already there and were left as they are.',
            $result['categories'],
            $result['products'],
            $result['unchanged'],
        ));

        if ($result['products'] > 0) {
            $this->components->warn('The prices are samples: review them before selling from the catalog.');
        }

        return self::SUCCESS;
    }
}
