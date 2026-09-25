<?php

use App\Modules\Accounts\AccountsServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Purchases\PurchasesServiceProvider;
use App\Modules\Reports\ReportsServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use App\Modules\Suppliers\SuppliersServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | What this installation is *about*. Everything around them — areas, guards,
    | sessions, money, the component library — is the foundation, and does not
    | know which of these are running.
    |
    | Order matters only for display: the admin sidebar shows their sections in
    | the order listed here, after the foundation's own Overview and before its
    | System section.
    |
    | Each entry is the module's service provider. Adding a module is one
    | folder and one line here.
    |
    | Removing the line *disables* the module: its routes, screens, strings,
    | permissions, sidebar section, configuration, and migrations stop
    | loading. Its data stays — its tables and rows, the foreign keys other
    | modules hold on them, and its rows in the permission tables — because
    | deleting data is not something a config edit should do.
    | Uninstalling is a separate, deliberate step; see ARCHITECTURE.md.
    |
    */

    'enabled' => [
        CatalogServiceProvider::class,
        CustomersServiceProvider::class,
        AccountsServiceProvider::class,
        SuppliersServiceProvider::class,
        PurchasesServiceProvider::class,
        SalesServiceProvider::class,
        ReportsServiceProvider::class,
    ],

];
