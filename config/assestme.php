<?php

declare(strict_types=1);

$releaseVersionPath = base_path('VERSION');
$releaseVersion = is_file($releaseVersionPath) ? trim((string) file_get_contents($releaseVersionPath)) : 'development';

return [
    'version' => env('ASSESTME_VERSION', $releaseVersion !== '' ? $releaseVersion : 'development'),

    'installation' => [
        'state_path' => storage_path('framework/installer/state.enc'),
        'bootstrap_key_path' => storage_path('framework/installer/bootstrap-key'),
        'lock_path' => storage_path('app/private/installed.lock'),
        'finalization_lock_path' => storage_path('framework/installer/finalize.lock'),
        'environment_path' => base_path('.env'),
        'php_binary' => env('ASSESTME_PHP_BINARY'),
    ],

    'development_administrator' => [
        'name' => env('DEV_ADMIN_NAME', 'Administrator'),
        'email' => env('DEV_ADMIN_EMAIL', 'admin@assestme.local'),
        'password' => env('DEV_ADMIN_PASSWORD'),
    ],

    'backup' => [
        'root' => env('ASSESTME_BACKUP_ROOT', storage_path('backups')),
        'private_storage_path' => storage_path('app/private'),
        'dump_binary' => env('ASSESTME_DB_DUMP_BINARY'),
        'restore_binary' => env('ASSESTME_DB_RESTORE_BINARY'),
        'retention' => [
            'daily' => 7,
            'weekly' => 4,
            'monthly' => 6,
        ],
    ],

    'deletion' => [
        'trash_root' => storage_path('app/private/.trash'),
    ],

    'scheduler' => [
        'heartbeat_path' => storage_path('app/private/operational-status/scheduler-heartbeat.json'),
        'fresh_for_seconds' => 150,
    ],
];
