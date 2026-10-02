<?php

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| The docs are tested like the code
|--------------------------------------------------------------------------
|
| The design before this one was described in two long documents that had
| drifted from the code — they sent readers to namespaces that no longer
| existed, a column that had been renamed, and a sign-up form that had been
| removed, while also saying there was no sign-up. The next model to read
| them would have followed them literally. These tests make a stale
| reference fail the build, in the same change that made it stale.
|
*/

/**
 * The documents a person or a model is told to read, keyed by path.
 *
 * Only the project's own block of AGENTS.md: the rest is written by Boost.
 *
 * @return array<string, string>
 */
function projectDocuments(): array
{
    $agents = (string) file_get_contents(base_path('AGENTS.md'));

    $documents = [
        'AGENTS.md' => Str::between($agents, '<project-guidelines>', '</project-guidelines>'),
        'ARCHITECTURE.md' => (string) file_get_contents(base_path('ARCHITECTURE.md')),
        'README.md' => (string) file_get_contents(base_path('README.md')),
    ];

    // The project's own skill, as written. Boost copies it, with its own
    // skills, into each agent's folder.
    foreach (glob(base_path('.ai/skills/ui-ux-development/{,references/}*.md'), GLOB_BRACE) ?: [] as $file) {
        $documents[projectRelativePath($file)] = (string) file_get_contents($file);
    }

    foreach (glob(base_path('docs/*.md')) ?: [] as $file) {
        $documents['docs/'.basename($file)] = (string) file_get_contents($file);
    }

    return $documents;
}

/**
 * Everything written in `code spans` and fenced blocks.
 *
 * @return list<string>
 */
function codeIn(string $markdown): array
{
    preg_match_all('/```.*?```|`[^`\n]+`/s', $markdown, $matches);

    return $matches[0];
}

/**
 * A name used only as an illustration, or named only to say it is gone.
 */
function isIllustration(string $reference): bool
{
    // The recipe's example module, which does not exist on purpose.
    if (str_contains($reference, 'Deliveries')) {
        return true;
    }

    // Named in the docs precisely to say they are not there.
    return in_array(rtrim($reference, '/\\'), ['app/Providers', 'App\\Models', 'app/Http', 'app/Support'], true);
}

test('every class the docs name exists', function () {
    $missing = [];

    foreach (projectDocuments() as $document => $markdown) {
        foreach (codeIn($markdown) as $code) {
            preg_match_all('/App(?:\\\\[A-Z][A-Za-z0-9_]*)+/', $code, $names);

            foreach ($names[0] as $name) {
                $path = app_path(str_replace('\\', '/', Str::after($name, 'App\\')));

                $exists = class_exists($name) || interface_exists($name) || enum_exists($name)
                    || trait_exists($name) || is_dir($path);

                if (! $exists && ! isIllustration($name)) {
                    $missing[] = "{$document}: {$name}";
                }
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

test('every path the docs name exists', function () {
    $missing = [];

    foreach (projectDocuments() as $document => $markdown) {
        foreach (codeIn($markdown) as $code) {
            // A path standing alone in a code span — not a pattern with a
            // placeholder in it, which describes a shape rather than a file.
            // `BASE/` is not checked: the AdminLTE template is git-ignored, so
            // a fresh clone does not have it.
            $candidate = trim($code, '`');

            if (! preg_match('#^(app|bootstrap|config|database|docs|lang|resources|routes|tests)/[^\s{}*<>…]*$#', $candidate)) {
                continue;
            }

            if (! file_exists(base_path(rtrim($candidate, '/'))) && ! isIllustration($candidate)) {
                $missing[] = "{$document}: {$candidate}";
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

test('every link between documents resolves', function () {
    $broken = [];

    foreach (projectDocuments() as $document => $markdown) {
        preg_match_all('/\]\(([^)#\s]+)(?:#[^)]*)?\)/', $markdown, $links);

        foreach ($links[1] as $link) {
            if (Str::startsWith($link, ['http://', 'https://', 'mailto:'])) {
                continue;
            }

            if (! file_exists(base_path(dirname($document).'/'.$link))) {
                $broken[] = "{$document} → {$link}";
            }
        }
    }

    expect($broken)->toBe([]);
});
