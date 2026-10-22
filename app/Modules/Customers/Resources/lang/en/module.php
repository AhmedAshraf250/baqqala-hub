<?php

return [
    'name' => 'Customers',

    'permissions' => [
        'view' => 'View customers',
        'create' => 'Add new customers',
        'manage' => 'Manage customers and groups',
        'grant_portal_access' => 'Grant portal access',
    ],

    'audit' => [
        'customer' => 'Customer',
        'fields' => [
            'name' => 'Name',
            'phone' => 'Phone',
            'address' => 'Address',
            'notes' => 'Notes',
        ],
    ],

    'group_color' => [
        'blue' => 'Blue',
        'green' => 'Green',
        'amber' => 'Amber',
        'red' => 'Red',
        'purple' => 'Purple',
        'teal' => 'Teal',
        'pink' => 'Pink',
        'gray' => 'Grey',
    ],

];
