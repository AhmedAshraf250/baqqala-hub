<?php

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

if (! function_exists('is_rtl')) {
    /**
     * Whether the active locale reads right to left.
     */
    function is_rtl(?string $locale = null): bool
    {
        return text_direction($locale) === 'rtl';
    }
}
