<?php

/*
|--------------------------------------------------------------------------
| The foundation
|--------------------------------------------------------------------------
|
| What every product built on the foundation reads, whatever it is about: its
| version, the languages it speaks, and the money it counts. Nothing here
| names the product — its name is APP_NAME, and on screen the
| `shell.brand.name` string.
|
*/

return [

    'version' => env('APP_VERSION', '0.1.0'),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Arabic and English are both first-class. `direction` drives which AdminLTE
    | stylesheet the admin shell loads and which chevrons the sidebar uses.
    |
    */

    'locales' => [
        'default' => env('APP_LOCALE', 'ar'),
        'fallback' => env('APP_FALLBACK_LOCALE', 'en'),
        'supported' => [
            'ar' => [
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'direction' => 'rtl',
            ],
            'en' => [
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
            ],
        ],

        /*
        | Each area keeps its own reader's choice, and may start from its own
        | default. `negotiate` lets a first-time visitor's browser language
        | decide before the default does — off, because many who read Arabic
        | browse on phones set to English.
        */
        'areas' => [
            'admin' => [
                'default' => env('ADMIN_LOCALE'),
                'negotiate' => (bool) env('ADMIN_LOCALE_NEGOTIATE', false),
            ],
            'frontend' => [
                'default' => env('FRONTEND_LOCALE'),
                'negotiate' => (bool) env('FRONTEND_LOCALE_NEGOTIATE', false),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | `precision` is how many decimal places a displayed amount carries, and it
    | is also how many minor units make up one major unit in storage. Changing
    | it after go-live requires migrating every stored money column.
    |
    | `symbol` is how the currency is written beside an amount, in each
    | language the shop reads in; a language with none shows the code.
    |
    */

    'currency' => [
        'code' => env('CURRENCY_CODE', 'EGP'),
        'symbol' => [
            'ar' => env('CURRENCY_SYMBOL_AR', 'ج.م'),
            'en' => env('CURRENCY_SYMBOL_EN', 'EGP'),
        ],
        'precision' => (int) env('CURRENCY_PRECISION', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local time
    |--------------------------------------------------------------------------
    |
    | Moments are stored in UTC (`app.timezone`, which stays UTC). These say how
    | they are shown and where "today" begins: the local time zone, and the
    | local time the business day starts — later than midnight for a shop that
    | closes after it. Both are defaults: the owner changes them from the
    | admin settings, and App\Foundation\Time\LocalClock reads the change.
    |
    */

    'time' => [
        'timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Cairo'),
        'day_starts_at' => env('APP_DAY_STARTS_AT', '00:00'),
    ],

];
