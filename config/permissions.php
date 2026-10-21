<?php

/*
|--------------------------------------------------------------------------
| Permissions & default roles
|--------------------------------------------------------------------------
|
| The single source of truth for permission names. Run
| `php artisan permissions:sync` after editing this file: it creates any
| missing permissions/roles and attaches the default permissions below.
| It never removes permissions that admins granted manually from the UI.
|
*/

return [

    // Users holding this role bypass every permission check.
    'super_admin_role' => 'admin',

    // Role given to everyone who registers (email or Google).
    'default_role' => 'user',

    'permissions' => [
        'admin' => [
            'admin.access' => 'Access the admin panel',
            'dashboard.view' => 'View dashboard statistics',
        ],
        'visitors' => [
            'visitors.view' => 'View visitors',
            'visitors.create' => 'Create visitors',
            'visitors.update' => 'Edit visitors',
            'visitors.delete' => 'Delete visitors',
            'visitors.link' => 'Link visitors to user accounts',
        ],
        'visits' => [
            'visits.view' => 'View the visits list',
            'visits.create' => 'Register visitor entrance',
            'visits.delete' => 'Cancel / delete visits',
            'visits.override_price' => 'Change the calculated price manually',
        ],
        'subscriptions' => [
            'subscriptions.view' => 'View subscriptions',
            'subscriptions.create' => 'Sell subscriptions',
            'subscriptions.cancel' => 'Cancel subscriptions',
        ],
        'pricing' => [
            'pricing.view' => 'View price list',
            'pricing.manage' => 'Edit prices, coefficients, age groups and time slots',
        ],
        'users' => [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Edit users, their roles and permissions',
            'users.delete' => 'Delete users',
        ],
        'roles' => [
            'roles.view' => 'View roles and permissions',
            'roles.manage' => 'Create, edit and delete roles',
        ],
        'activity' => [
            'activity.view' => 'View the activity log',
        ],
    ],

    'roles' => [
        'admin' => [
            'display_name' => 'Admin',
            'description' => 'Full access to everything.',
            'permissions' => ['*'],
        ],
        'worker' => [
            'display_name' => 'Worker',
            'description' => 'Gym staff: registers visitors and their entrances.',
            'permissions' => [
                'admin.access', 'dashboard.view',
                'visitors.view', 'visitors.create', 'visitors.update',
                'visits.view', 'visits.create',
                'subscriptions.view', 'subscriptions.create',
                'pricing.view',
            ],
        ],
        'user' => [
            'display_name' => 'User',
            'description' => 'Registered climber. Can see own profile and visits.',
            'permissions' => [],
        ],
    ],
];
