<?php

declare(strict_types=1);

return [
    'version' => env('ASSESTME_VERSION', 'development'),

    'development_administrator' => [
        'name' => env('DEV_ADMIN_NAME', 'Administrator'),
        'email' => env('DEV_ADMIN_EMAIL', 'admin@assestme.local'),
        'password' => env('DEV_ADMIN_PASSWORD'),
    ],

    'backup' => [
        'root' => env('ASSESTME_BACKUP_ROOT', storage_path('backups')),
        'private_storage_path' => storage_path('app/private'),
        'retention' => [
            'daily' => 7,
            'weekly' => 4,
            'monthly' => 6,
        ],
    ],

    'deletion' => [
        'trash_root' => storage_path('app/private/.trash'),
    ],
];
