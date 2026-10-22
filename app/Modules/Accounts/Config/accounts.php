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
    // a deposit against future purchases. On: the counter asks, for each
    // payment larger than the debt, whether the rest was change or credit.
    // Off: only change — a payment can never leave the shop owing.
    'allow_overpayment' => filter_var(
        env('ACCOUNTS_ALLOW_OVERPAYMENT', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    // How long, in minutes, whoever recorded an entry may void or correct it
    // without `accounts.correct`.
    'correction_window_minutes' => (int) env('ACCOUNTS_CORRECTION_WINDOW_MINUTES', 15),

    // When a debt counts as late: this many days owed without a payment.
    // Worked out from the account's summary whenever a screen asks, never
    // stored, so changing it changes every account at once.
    'late_after_days' => (int) env('ACCOUNTS_LATE_AFTER_DAYS', 30),

    // When an account counts as dormant: this many days without an entry.
    'dormant_after_days' => (int) env('ACCOUNTS_DORMANT_AFTER_DAYS', 90),

    // How recent an entry of the same amount for the same customer has to
    // be for the counter to be asked whether this one is new or the same one
    // recorded twice.
    'duplicate_warning_seconds' => (int) env('ACCOUNTS_DUPLICATE_WARNING_SECONDS', 120),

    'transaction_reference_prefix' => env('ACCOUNTS_TRANSACTION_REFERENCE_PREFIX', 'TRX'),

];
