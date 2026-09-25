<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer Accounts
    |--------------------------------------------------------------------------
    |
    | The module's defaults, read as `config('accounts.…')`. An installation
    | overrides any of them with a config/accounts.php of its own — the same
    | file name, so the override is obvious.
    |
    */

    // The most a new account may owe, as a decimal string parsed through
    // Money. "0.00" means no ceiling is configured.
    'default_credit_limit' => env('ACCOUNTS_DEFAULT_CREDIT_LIMIT', '0.00'),

    // Whether a customer may pay more than they owe, leaving the shop holding
    // a deposit against future purchases.
    'allow_overpayment' => filter_var(
        env('ACCOUNTS_ALLOW_OVERPAYMENT', false),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'transaction_reference_prefix' => env('ACCOUNTS_TRANSACTION_REFERENCE_PREFIX', 'TRX'),

];
