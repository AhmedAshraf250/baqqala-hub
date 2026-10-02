<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run anything outside the testing environment.
     *
     * A cached configuration (`php artisan optimize`, `config:cache`) is read
     * instead of phpunit.xml, so the suite would run on the developer's own
     * database — and refreshing it wipes every row. That happened once; this
     * stops it before the first query.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing')) {
            throw new RuntimeException(
                "The tests are running in [{$app->environment()}], not [testing], so they would use and wipe the real database. "
                .'The configuration is probably cached: run `php artisan optimize:clear` first.'
            );
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
