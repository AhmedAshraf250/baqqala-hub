<?php

use App\Foundation\Area\Area;
use App\Foundation\Identity\Models\AdminUser;
use App\Foundation\Identity\Models\FrontendUser;
use Database\Seeders\AuthorizationSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
    })
    ->in('Feature');

// A real browser needs the real assets, so these keep Vite.
pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->afterEach(function (): void {
        stopOrphanedPlaywrightServers();
    })
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in every test file.
|
*/

/**
 * Sign in as an administrator who owns the shop.
 *
 * Two things a test would otherwise repeat. The areas use different guards, so
 * `actingAs($user)` alone puts the user on the *customer* guard and every admin
 * route bounces them. And admin sections are permission-guarded, so an
 * administrator with no role reaches nothing — the default here is the
 * unrestricted role, which is what "an admin" means in a test that is not
 * about permissions.
 *
 * Tests that are about permissions assign a narrower role themselves.
 */
function actingAsAdmin(?AdminUser $user = null): AdminUser
{
    $owner = (string) config('roles.unrestricted');

    if (Role::query()->where('name', $owner)->doesntExist()) {
        test()->seed(AuthorizationSeeder::class);
    }

    $user ??= AdminUser::factory()->create();
    $user->syncRoles($owner);

    test()->actingAs($user, Area::Admin->guard());

    return $user;
}

/**
 * Sign in as a customer on the customer guard.
 */
function actingAsCustomer(?FrontendUser $user = null): FrontendUser
{
    $user ??= FrontendUser::factory()->create();

    test()->actingAs($user, Area::Frontend->guard());

    return $user;
}

/**
 * Every routed admin screen, so a new page cannot quietly skip these checks.
 *
 * @return list<string>
 */
function adminScreens(): array
{
    return [
        'admin.dashboard',
        'admin.settings',
        'admin.profile',
        // One module screen, to prove a module's route renders in the shell
        // exactly like the shell's own.
        'admin.catalog.products.index',
    ];
}

/**
 * Every routed customer screen actually rendering.
 *
 * The security screen once returned a 500 because a relation on the guard's
 * model looked for `passkeys.customer_user_id`. Nothing rendered these pages
 * end to end, so nothing caught it.
 *
 * @return list<string>
 */
function customerScreens(): array
{
    return [
        'frontend.account.dashboard',
        'frontend.account.settings.profile',
        'frontend.account.settings.appearance',
        'frontend.account.settings.security',
    ];
}

/**
 * Stop the Playwright servers this project started that no run owns any more.
 *
 * The browser plugin starts its server through `sh -c` and stops only the
 * shell (pestphp/pest#1754). Where /bin/sh is dash — Debian, Ubuntu — the Node
 * server outlives the run, about 200 MB each, and holds open any pipe the
 * output goes to, so `php artisan test | tail` never returns. A run clears what
 * earlier runs left, since one that is killed outright cannot clean up after
 * itself, and clears its own when it ends. A server whose shell is still alive
 * belongs to a run in progress and is left alone. Delete this once
 * pestphp/pest-plugin-browser#169 is released.
 */
function stopOrphanedPlaywrightServers(): void
{
    static $started = false;

    if ($started || PHP_OS_FAMILY !== 'Linux') {
        return;
    }

    $started = true;
    $project = base_path();

    $parentOf = static fn (int $pid): int => (int) (explode(' ', trim(Str::afterLast((string) @file_get_contents("/proc/{$pid}/stat"), ')')))[1] ?? 0);
    $commandOf = static fn (int $pid): string => basename(explode("\0", (string) @file_get_contents("/proc/{$pid}/cmdline"))[0]);

    $stop = static function () use ($project, $parentOf, $commandOf): void {
        $servers = new Process(['pgrep', '-f', 'playwright run-server --host']);
        $servers->run();

        foreach (array_filter(explode("\n", $servers->getOutput())) as $pid) {
            // The shell that launched a server matches the pattern too; only
            // the Node process is the server. (Node renames its main thread,
            // so `comm` says MainThread; the command line says node.)
            if ($commandOf((int) $pid) !== 'node') {
                continue;
            }

            // Owned means Node → the plugin's shell → a PHP process still
            // running. A run killed outright leaves the shell alive but
            // without its PHP parent.
            $shell = $parentOf((int) $pid);
            $owned = in_array($commandOf($shell), ['sh', 'dash', 'bash'], true)
                && str_starts_with($commandOf($parentOf($shell)), 'php');

            if (! $owned && @readlink("/proc/{$pid}/cwd") === $project) {
                // The browser is already closed; a lone SIGTERM is shrugged off.
                posix_kill((int) $pid, SIGKILL);
            }
        }
    };

    // Registered after the first test, so it runs after the plugin's own
    // shutdown has let go of the server.
    $stop();
    register_shutdown_function($stop);
}

/**
 * The built filename for a Vite entry, read from the manifest.
 */
function builtAsset(string $entry): string
{
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

    return $manifest[$entry]['file'] ?? throw new RuntimeException("No manifest entry for [{$entry}].");
}

/**
 * What a screen reader or keyboard user would trip on, as far as markup alone
 * decides it: a link or button with no name, a field with no label, a
 * reference to an id that is not there, an image with no alt.
 *
 * Contrast, focus order, and motion need a browser; this does not see them.
 *
 * @return list<string>
 */
function accessibilityProblems(string $html): array
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    $problems = [];

    $describe = static fn (Element $element): string => Str::limit(preg_replace('/\s+/', ' ', $document->saveHtml($element)) ?? '', 160);

    // The text a screen reader would read, skipping what is hidden from it.
    $spokenText = static function (Node $node) use (&$spokenText): string {
        if ($node instanceof Element) {
            if ($node->getAttribute('aria-hidden') === 'true') {
                return '';
            }

            if ($node->localName === 'img') {
                return (string) $node->getAttribute('alt');
            }
        }

        if ($node instanceof Text) {
            return $node->textContent ?? '';
        }

        return implode('', array_map($spokenText, iterator_to_array($node->childNodes)));
    };

    $named = static fn (Element $element): bool => filled($element->getAttribute('aria-label'))
        || filled($element->getAttribute('aria-labelledby'))
        || filled($element->getAttribute('title'))
        || filled(trim($spokenText($element)))
        // Flux names a trigger with no text by its tooltip, in the browser.
        || filled(trim((string) $element->closest('ui-tooltip')?->querySelector('[data-flux-tooltip-content]')?->textContent));

    foreach ($document->querySelectorAll('a[href], button, [role="button"]') as $control) {
        if (! $named($control)) {
            $problems[] = "No accessible name: {$describe($control)}";
        }
    }

    $fields = 'input:not([type="hidden"], [type="submit"], [type="button"], [type="reset"], [type="image"]), select, textarea';

    foreach ($document->querySelectorAll($fields) as $field) {
        $id = $field->getAttribute('id');

        $labelled = filled($field->getAttribute('aria-label'))
            || filled($field->getAttribute('aria-labelledby'))
            || $field->closest('label') !== null
            || (filled($id) && $document->querySelector('label[for="'.addslashes($id).'"]') !== null)
            // Flux pairs its <ui-label> with the control in the browser.
            || $field->closest('[data-flux-field]')?->querySelector('[data-flux-label]') !== null;

        if (! $labelled) {
            $problems[] = "No label: {$describe($field)}";
        }
    }

    foreach ($document->querySelectorAll('[aria-describedby], [aria-labelledby], [aria-controls], label[for]') as $element) {
        foreach (['aria-describedby', 'aria-labelledby', 'aria-controls', 'for'] as $attribute) {
            foreach (preg_split('/\s+/', trim((string) $element->getAttribute($attribute))) ?: [] as $id) {
                if ($id !== '' && $document->getElementById($id) === null) {
                    $problems[] = "{$attribute} points at a missing #{$id}: {$describe($element)}";
                }
            }
        }
    }

    foreach ($document->querySelectorAll('img:not([alt])') as $image) {
        $problems[] = "No alt: {$describe($image)}";
    }

    return $problems;
}

/**
 * A fresh application, booted with the given modules in place of
 * config/modules.php.
 *
 * For proving what a module takes with it: a registry built by hand would
 * only prove the registry. This boots the real thing — providers, routes,
 * views, translations, configuration — the way a request would see it.
 *
 * @param  list<class-string>  $modules
 */
function applicationWithModules(array $modules): Application
{
    $app = require base_path('bootstrap/app.php');

    $app->afterBootstrapping(
        LoadConfiguration::class,
        static fn (Application $app) => $app['config']->set('modules.enabled', $modules),
    );

    $app->make(ConsoleKernel::class)->bootstrap();

    return $app;
}
