<?php

return [
    'name' => 'Accounts',

    'navigation' => [
        'accounts' => 'Accounts',
    ],

    'permissions' => [
        'view' => 'View accounts',
        'post' => 'Post account entries',
    ],

    'planned' => [
        'accounts' => 'Statements, and recording what is taken on account and what is paid.',
    ],

    'transaction_type' => [
        'debit' => 'Owes',
        'credit' => 'Paid',
    ],

    'transaction_action' => [
        'debit' => 'Taken on account',
        'credit' => 'Paid off the account',
    ],
];
