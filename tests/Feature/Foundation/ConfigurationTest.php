<?php

test('the foundation and the admin area expose their defaults', function () {
    expect(config('foundation.locales.supported.ar.direction'))->toBe('rtl')
        ->and(config('foundation.locales.supported.en.direction'))->toBe('ltr')
        ->and(config('foundation.currency.precision'))->toBe(2)
        ->and(config('admin.theme_storage_key'))->toBeString();
});

test('the configured default locale is one the application supports', function () {
    expect(config('foundation.locales.supported'))
        ->toHaveKey(config('foundation.locales.default'))
        ->toHaveKey(config('foundation.locales.fallback'));
});

test('every translation key resolves in every supported locale', function () {
    $keys = [
        'shell.brand.name',
        'shell.navigation.dashboard',
        'shell.actions.login',
        'shell.page.home.heading',
        'shell.roles.owner',
        'foundation.areas.admin',
        'accounts::module.transaction_type.debit',
        'catalog::module.name',
    ];

    foreach (array_keys(config('foundation.locales.supported')) as $locale) {
        app()->setLocale($locale);

        foreach ($keys as $key) {
            expect(__($key))->not->toBe($key, "Missing [{$key}] for locale [{$locale}].");
        }
    }
});

test('the arabic and english translation files define the same keys', function (string $directory) {
    // The shell's strings and every module's own, file by file.
    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $keys = array_merge($keys, is_array($value) ? $flatten($value, $path) : [$path]);
        }

        sort($keys);

        return $keys;
    };

    $files = fn (string $locale): array => array_map('basename', glob("{$directory}/{$locale}/*.php") ?: []);

    expect($files('en'))->toBe($files('ar'))->not->toBeEmpty();

    foreach ($files('ar') as $file) {
        expect($flatten(require "{$directory}/ar/{$file}"))
            ->toBe($flatten(require "{$directory}/en/{$file}"), "[{$directory}/{$file}] differs between ar and en.");
    }
})->with(function (): array {
    // Built from the file system, not the container: a dataset is resolved
    // before the application boots.
    $root = dirname(__DIR__, 3);

    return [$root.'/lang', ...glob($root.'/app/Modules/*/Resources/lang') ?: []];
});

test('every environment key the configuration reads is documented', function () {
    // A setting that only exists in someone's local .env is a setting the next
    // person deploying does not know about.
    $documented = preg_split('/\R/', (string) file_get_contents(base_path('.env.example')));

    $declared = [];

    // Each layer's settings, the roles, and every module's own defaults.
    $files = [config_path('foundation.php'), config_path('admin.php'), config_path('frontend.php'), config_path('modules.php'), config_path('roles.php'), ...glob(app_path('Modules/*/Config/*.php')) ?: []];

    foreach ($files as $file) {
        preg_match_all("/env\('([A-Z_]+)'/", (string) file_get_contents($file), $matches);
        $declared = [...$declared, ...$matches[1]];
    }

    $missing = array_values(array_filter(
        array_unique($declared),
        static fn (string $key): bool => ! array_filter(
            $documented,
            static fn (string $line): bool => str_starts_with(trim($line), $key.'='),
        ),
    ));

    expect($missing)->toBe([]);
});
