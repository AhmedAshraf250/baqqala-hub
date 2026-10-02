<?php

namespace App\Foundation\Localization;

use App\Foundation\Area\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * The reader's chosen language, per area.
 *
 * Each area is its own application with its own readers — the shop's staff in
 * one, its customers in the other — so each keeps its own choice, the way each
 * keeps its own session. It is a cookie rather than a session key so that it
 * outlives signing out, and it is copied onto the login so that it follows the
 * person to another device.
 *
 * Every value is checked against `foundation.locales.supported` on the way in
 * and on the way out, so an arbitrary one never reaches `App::setLocale()`.
 */
final class LocalePreference
{
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
     *
     * @phpstan-assert-if-true =string $locale
     */
    public static function isSupported(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, self::supported(), true);
    }

    /**
     * Remember a reader's choice in an area: in the area's cookie, and on the
     * login signed in to that area, if there is one.
     */
    public static function remember(Area $area, string $locale): void
    {
        if (! self::isSupported($locale)) {
            return;
        }

        Cookie::queue($area->localeCookie(), $locale, self::Lifetime);

        $area->user()?->forceFill(['locale' => $locale])->save();
    }

    /**
     * The locale to render this request in.
     *
     * The first that answers wins: the signed-in login's own choice, then this
     * browser's choice in this area, then — where the area allows it — the
     * browser's language, then the area's default, then the application's.
     */
    public static function resolve(Request $request, Area $area): string
    {
        $candidates = [
            $area->user()?->locale,
            // A cookie can arrive as an array when someone crafts one;
            // isSupported() checks the type before the value.
            $request->cookie($area->localeCookie()),
            self::negotiate($request, $area),
            self::defaultFor($area),
        ];

        foreach ($candidates as $candidate) {
            if (self::isSupported($candidate)) {
                return $candidate;
            }
        }

        return (string) config('foundation.locales.default');
    }

    /**
     * The language an area opens in before its reader has chosen one.
     */
    public static function defaultFor(Area $area): string
    {
        $default = config("foundation.locales.areas.{$area->value}.default");

        return self::isSupported($default) ? $default : (string) config('foundation.locales.default');
    }

    /**
     * The first language the browser asks for that this application has, when
     * the area lets the browser decide.
     */
    private static function negotiate(Request $request, Area $area): ?string
    {
        if (! config("foundation.locales.areas.{$area->value}.negotiate", false)) {
            return null;
        }

        foreach ($request->getLanguages() as $language) {
            // `en_US` and `en` both mean English to an application that has
            // only one English.
            $primary = strtolower(strtok($language, '_-') ?: '');

            if (self::isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
