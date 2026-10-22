<?php

return [
    'name' => 'العملاء',

    'permissions' => [
        'view' => 'عرض العملاء',
        'create' => 'إضافة عملاء جدد',
        'manage' => 'إدارة العملاء والمجموعات',
        'grant_portal_access' => 'منح العميل حساب دخول',
    ],

    'audit' => [
        'customer' => 'عميل',
        'fields' => [
            'name' => 'الاسم',
            'phone' => 'الموبايل',
            'address' => 'العنوان',
            'notes' => 'ملاحظات',
        ],
    ],

    'group_color' => [
        'blue' => 'أزرق',
        'green' => 'أخضر',
        'amber' => 'كهرماني',
        'red' => 'أحمر',
        'purple' => 'بنفسجي',
        'teal' => 'فيروزي',
        'pink' => 'وردي',
        'gray' => 'رمادي',
    ],

];
