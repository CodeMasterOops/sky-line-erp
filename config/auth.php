<?php

return [
    'guards' => [
        'super_admin' => [
            'driver' => 'sanctum',
            'provider' => 'super_admins',
            'table' => 'personal_access_tokens',
        ],
        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'users',
            'table' => 'personal_access_tokens',
        ],
    ],

    'providers' => [
        'super_admins' => [
            'driver' => 'eloquent',
            'model' => \App\Models\SuperAdmin::class,
        ],
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
