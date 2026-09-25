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
    */

    'currency' => [
        'code' => env('CURRENCY_CODE', 'EGP'),
        'symbol' => env('CURRENCY_SYMBOL', 'ج.م'),
        'precision' => (int) env('CURRENCY_PRECISION', 2),
    ],

];
