<?php

/*
 * Tailwind builds the frontend bundle from the files it is told to read. The
 * stylesheet once named four folders that had since moved, so it read none of
 * them, fell back to scanning the whole project, and carried admin classes.
 */

/**
 * The frontend stylesheet's source.
 */
function frontendStylesheetSource(): string
{
    return (string) file_get_contents(resource_path('css/frontend/app.css'));
}

test('the frontend stylesheet scans only what it names', function () {
    expect(frontendStylesheetSource())->toContain("@import 'tailwindcss' source(none);");
});

test('every folder the frontend stylesheet names is there', function () {
    preg_match_all("/^@source '([^']+)';/m", frontendStylesheetSource(), $sources);

    expect($sources[1])->not->toBeEmpty();

    $missing = collect($sources[1])
        // A pattern may match nothing yet: no module has frontend views today.
        ->reject(fn (string $source): bool => str_contains($source, '*'))
        ->reject(fn (string $source): bool => file_exists(resource_path('css/frontend/'.$source)))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
