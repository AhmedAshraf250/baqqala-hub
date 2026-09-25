<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrator Roles
    |--------------------------------------------------------------------------
    |
    | The roles a new installation starts with, and what each may do. This is
    | the product's opinion about who works here, so it sits beside
    | config/modules.php rather than in code: a school ships different roles
    | over the same foundation.
    |
    | Grants are patterns over permission names. `catalog.*` is everything the
    | Catalog module defines; a pattern naming a module this installation does
    | not run grants nothing. Roles are seeded, not fixed — an owner creates
    | more from the panel.
    |
    */

    'defaults' => [

        // Everything, including who else may do what.
        'owner' => ['*'],

        // Runs the shop day to day, but does not hand out access.
        'manager' => [
            'catalog.*',
            'customers.*',
            'accounts.*',
            'suppliers.*',
            'purchases.*',
            'sales.*',
            'reports.*',
            'settings.manage',
        ],

        // Serves customers: records what is taken on account, and takes payment.
        'cashier' => [
            'catalog.view',
            'customers.view',
            'accounts.view',
            'accounts.post',
            'sales.view',
            'sales.manage',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Unrestricted Role
    |--------------------------------------------------------------------------
    |
    | Granted every check without holding each permission, so a permission a
    | module adds tomorrow is the owner's the moment it exists.
    |
    */

    'unrestricted' => env('ADMIN_UNRESTRICTED_ROLE', 'owner'),

];
