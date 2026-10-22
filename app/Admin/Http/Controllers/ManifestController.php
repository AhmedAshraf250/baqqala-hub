<?php

namespace App\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * What a phone needs to add the panel to its home screen: a name, icons,
 * where it opens, and that it opens on its own, without the browser's bar.
 *
 * Its name is the brand's, in the reader's language, so renaming the shop
 * renames the icon too. The browser asks for it with the person's cookies —
 * the layout's link says so — because the panel is only for whoever signed
 * in.
 */
final class ManifestController
{
    public function __invoke(): JsonResponse
    {
        $name = __('shell.brand.name');
        $icon = static fn (string $file, string $size, string $purpose): array => [
            'src' => asset("admin-icons/{$file}"),
            'sizes' => $size,
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()->json([
            'id' => '/admin/',
            'name' => $name,
            'short_name' => $name,
            'description' => __('shell.brand.tagline'),
            'lang' => str_replace('_', '-', app()->getLocale()),
            'dir' => text_direction(),
            'start_url' => route('admin.home', absolute: false),
            'scope' => '/admin/',
            'display' => 'standalone',
            'theme_color' => config('admin.install.theme_color'),
            'background_color' => config('admin.install.background_color'),
            'icons' => [
                $icon('icon-192.png', '192x192', 'any'),
                $icon('icon-512.png', '512x512', 'any'),
                $icon('maskable-512.png', '512x512', 'maskable'),
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
