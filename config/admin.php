<?php

/*
|--------------------------------------------------------------------------
| The admin area
|--------------------------------------------------------------------------
|
| The admin shell's own settings. Anything about one module lives in that
| module's Config/{key}.php, read as `config('{key}.…')`; who may do what is
| config/roles.php.
|
*/

return [

    // Where the browser remembers the chosen colour mode.
    'theme_storage_key' => 'admin.theme',

    'per_page' => (int) env('ADMIN_PER_PAGE', 15),

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    'search' => [
        'min_query_length' => (int) env('ADMIN_SEARCH_MIN_QUERY_LENGTH', 2),
        'max_results_per_group' => (int) env('ADMIN_SEARCH_MAX_RESULTS_PER_GROUP', 8),
        'normalize_arabic' => filter_var(
            env('ADMIN_SEARCH_NORMALIZE_ARABIC', true),
            FILTER_VALIDATE_BOOLEAN,
        ),
    ],

];
