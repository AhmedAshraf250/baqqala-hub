<?php

namespace App\Foundation\Localization;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The reader's chosen language.
 *
 * A property of the person, not of the area they happen to be reading, so it
 * lives in a cookie of its own rather than in the session — each area has its
 * own session, and a choice made in one would otherwise never reach the other.
 *
 * Validated against `foundation.locales.supported` on the way in and on the way
 * out, so an arbitrary value never reaches `App::setLocale()`.
 */
final class LocalePreference
{
    public const Cookie = 'locale';

    /**
     * A year, in minutes. A language is not something people re-pick.
     */
    private const Lifetime = 525600;

    /**
     * Every locale the application is willing to render.
     *
     * @return list<string>
     */
    public static function supported(): array
    {
        // Locale codes are the array keys, and PHP widens those to int|string.
        return array_map(
            static fn (int|string $code): string => (string) $code,
            array_keys(config('foundation.locales.supported', [])),
        );
    }

    /**
     * Whether the given value is a locale this application supports.
     */
    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    /**
     * Remember a locale for this reader, from the next response on.
     */
    public static function store(string $locale): void
    {
        if (self::isSupported($locale)) {
            Cookie::queue(self::Cookie, $locale, self::Lifetime);
        }
    }

    /**
     * The locale to render this request in.
     */
    public static function resolve(Request $request): string
    {
        // A cookie can arrive as an array when someone crafts one, so the type
        // is checked before the value.
        $stored = $request->cookie(self::Cookie);

        return is_string($stored) && self::isSupported($stored)
            ? $stored
            : (string) config('foundation.locales.default');
    }
}
