<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    |
    | The module's defaults, read as `config('catalog.…')`. An installation
    | overrides any of them with a config/catalog.php of its own — the same
    | file name, so the override is obvious.
    |
    */

    'sku_prefix' => env('CATALOG_SKU_PREFIX', 'BQ'),

    // At or below this many, a product counts as running low.
    'low_stock_threshold' => (int) env('CATALOG_LOW_STOCK_THRESHOLD', 5),

];
