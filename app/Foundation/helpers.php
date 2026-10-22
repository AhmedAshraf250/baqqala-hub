<?php

use App\Foundation\Time\LocalClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

if (! function_exists('text_direction')) {
    /**
     * The text direction for the active locale.
     */
    function text_direction(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return config("foundation.locales.supported.{$locale}.direction", 'ltr');
    }
}

if (! function_exists('local_time')) {
    /**
     * A stored moment on the local wall clock, for display.
     *
     * Moments are stored in UTC; a template shows them through this, so a
     * sale at 23:30 in Cairo never reads as 21:30 — or as the next day.
     */
    function local_time(?CarbonInterface $moment): ?CarbonImmutable
    {
        return $moment === null ? null : app(LocalClock::class)->local($moment);
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * How the shop's currency is written beside an amount in a language —
     * «ج.م» in Arabic, «EGP» in English — or its code where none is set.
     */
    function currency_symbol(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return (string) (config("foundation.currency.symbol.{$locale}") ?? config('foundation.currency.code'));
    }
}

if (! function_exists('is_rtl')) {
    /**
     * Whether the active locale reads right to left.
     */
    function is_rtl(?string $locale = null): bool
    {
        return text_direction($locale) === 'rtl';
    }
}
