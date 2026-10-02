<?php

use Illuminate\Support\Facades\Lang;

/**
 * Every Blade file under a directory, relative to the project root.
 *
 * @return list<string>
 */
function bladeFilesIn(string $directory): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory))) as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = projectRelativePath($file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * Every view the application renders: the two areas, Flux's overrides, and
 * whatever the modules carry.
 *
 * @return list<string>
 */
function allViews(): array
{
    return [
        ...bladeFilesIn('resources/views'),
        ...collect(glob(app_path('Modules/*/Resources/views')) ?: [])
            ->flatMap(fn (string $path) => bladeFilesIn(projectRelativePath($path)))
            ->all(),
    ];
}

test('every view belongs to an area', function () {
    // A view outside `admin/` and `frontend/` is in the one namespace both
    // areas can reach — how an admin was once handed a customer page. Flux's
    // component overrides are the exception, because Flux looks for them
    // there by name.
    $topLevel = collect(glob(resource_path('views/*')) ?: [])->map(fn (string $path) => basename($path))->values()->all();

    expect($topLevel)->toBe(['admin', 'flux', 'frontend']);
});

test('no view styles itself inline', function () {
    // Styling lives in the area's stylesheet, written once. An inline style is
    // a customisation that cannot be found, themed, or reused.
    $offenders = array_filter(
        allViews(),
        fn (string $view) => preg_match('/<style\b|\sstyle="|\s:style="|\sx-bind:style="/', (string) file_get_contents(base_path($view))) === 1,
    );

    expect(array_values($offenders))->toBe([]);
});

test('no view carries a script of its own', function () {
    // Behaviour lives in resources/js/{area}/modules. The one exception is the
    // colour-mode script, which has to run before the first paint or the page
    // flashes the wrong theme.
    $offenders = array_filter(
        allViews(),
        fn (string $view) => ! str_ends_with($view, 'color-mode-script.blade.php')
            && preg_match('/<script\b/', (string) file_get_contents(base_path($view))) === 1,
    );

    expect(array_values($offenders))->toBe([]);
});

test('no view uses a colour that fails contrast in one of the modes', function (string $pattern) {
    // Found by axe in tests/Browser; these are the ones markup alone decides.
    $offenders = array_filter(
        allViews(),
        fn (string $view) => preg_match($pattern, (string) file_get_contents(base_path($view))) === 1,
    );

    expect(array_values($offenders))->toBe([]);
})->with([
    // One grey in both modes: 4.3:1 on the light page, 3.8:1 on the dark.
    // Muted text is text-body-secondary, which follows the mode.
    'Bootstrap text-secondary' => '/\btext-secondary\b(?!-)/',
    // 2.5:1 in light mode; Flux's default text reads in both.
    'Flux subtle text' => '/<flux:text\b[^>]*variant="subtle"/',
]);

test('every string a view asks for is a key, and exists in both languages', function () {
    // A sentence passed to __() is shown as written in every language — which
    // is how a hundred English lines once sat in an Arabic portal. Views ask
    // for keys; keys exist in Arabic and in English.
    $sentences = [];
    $missing = [];

    foreach (allViews() as $view) {
        // Blade comments document usage — `__('...')` in one is an example.
        $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(base_path($view)));

        // Whole literals only: `__('shell.actions.theme_'.$value)` builds its
        // key at runtime, and the part written here is not a key on its own.
        preg_match_all('/__\(\s*([\'"])(.*?)(?<!\\\\)\1\s*[,)]/', $source, $calls);

        foreach ($calls[2] as $key) {
            if (str_contains($key, '$')) {
                continue;
            }

            if (preg_match('/^([a-z][a-z0-9_]*::)?[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $key) !== 1) {
                $sentences[] = "{$view}: {$key}";

                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                if (! Lang::has($key, $locale, false)) {
                    $missing[] = "{$view}: {$key} ({$locale})";
                }
            }
        }
    }

    expect($sentences)->toBe([])
        ->and($missing)->toBe([]);
});

test('no view writes a label or a heading as plain text', function () {
    // The same leak through an attribute: `label="OTP Code"` never reaches the
    // translator at all.
    $offenders = array_filter(
        allViews(),
        // Human text — a phrase, or a capitalised word. A format example such
        // as `placeholder="email@example.com"` is not translated anywhere.
        fn (string $view) => preg_match('/\s(label|placeholder|heading|subheading|description|separator)="(?=[^"{]*\s|[A-Z])[A-Za-z][^"{]*"/', (string) file_get_contents(base_path($view))) === 1,
    );

    expect(array_values($offenders))->toBe([]);
});

test('no template fetches what it shows', function (string $reach, string $pattern) {
    // A template that reaches into the application is logic hidden in markup —
    // the reason Magento moved from Blocks to view models. What a template
    // shows comes from its component's class (app/{Admin,Frontend}/View/Components,
    // a module's View/Components), its screen's controller, or its Livewire
    // component; the template renders it. Helpers that turn a value into
    // markup — __(), route(), config(), old(), @csrf — are not reaching.
    $offenders = [];

    foreach (allViews() as $view) {
        // Flux's overrides are Flux's own templates, written its way.
        if (str_starts_with($view, 'resources/views/flux/')) {
            continue;
        }

        $template = (string) file_get_contents(base_path($view));

        // A Livewire single-file component's class is its view model; only
        // the template after it is checked. Comments may name what they like.
        $template = (string) preg_replace(['/\A<\?php.*?\?>/s', '/\{\{--.*?--\}\}/s'], '', $template);

        if (preg_match($pattern, $template) === 1) {
            $offenders[] = $view;
        }
    }

    expect($offenders)->toBe([], "These templates reach for {$reach}.");
})->with([
    // Reading the locale for `<html lang>` is a value, not a service.
    'the container' => ['the container', '/\bapp\(\s*[^)\s]|\bapp\(\)->(?!getLocale\(\))|\bresolve\(\s*[\\\\\w]+::class|@inject\b|\bApp::/'],
    'the signed-in user' => ['the signed-in user', '/\bauth\(|\bAuth::|@auth\b|@guest\b/'],
    'the request' => ['the request', '/\brequest\(/'],
    'the session' => ['the session', '/\bsession\(/'],
    'a PHP class' => ['a PHP class', '/(?<![\w:<$-])\\\\?[A-Z]\w*(?:\\\\\w+)*::(?!class\b)\w/'],
]);
