<?php

return [
    'name' => 'Catalog',

    'navigation' => [
        'products' => 'Products',
        'categories' => 'Categories',
        'stock' => 'Stock',
    ],

    'permissions' => [
        'view' => 'View catalog',
        'manage' => 'Manage catalog',
        'adjust_stock' => 'Adjust stock',
    ],

    'planned' => [
        'products' => 'Manage products, prices, and the categories they belong to.',
        'categories' => 'Organise products into nested categories.',
        'stock' => 'Track quantities and record stock movements.',
    ],
];
