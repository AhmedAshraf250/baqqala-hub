<?php

use App\Admin\AdminServiceProvider;
use App\Foundation\FoundationServiceProvider;
use App\Frontend\FrontendServiceProvider;

/*
 * One provider per layer. The foundation's comes first because it registers
 * the modules, and each module is itself a service provider.
 */

return [
    FoundationServiceProvider::class,
    AdminServiceProvider::class,
    FrontendServiceProvider::class,
];
