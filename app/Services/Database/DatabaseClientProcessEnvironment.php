<?php

declare(strict_types=1);

namespace App\Services\Database;

use RuntimeException;

final class DatabaseClientProcessEnvironment
{
    /** @return array<string, string|false> */
    public function forDriver(string $driver, string $credentialsPath): array
    {
        $loginFile = $credentialsPath.'.disabled-login-path';

        if (file_exists($loginFile) || is_link($loginFile)) {
            throw new RuntimeException('The isolated database login-file path is not available.');
        }

        $environment = [
            'DATABASE_URL' => false,
            'DB_DATABASE' => false,
            'DB_HOST' => false,
            'DB_PASSWORD' => false,
            'DB_PORT' => false,
            'DB_SOCKET' => false,
            'DB_URL' => false,
            'DB_USERNAME' => false,
            'MARIADB_PASSWORD' => false,
            'MARIADB_PWD' => false,
            'MARIADB_ROOT_PASSWORD' => false,
            'MARIADB_USER' => false,
            'MYSQL_PASSWORD' => false,
            'MYSQL_PWD' => false,
            'MYSQL_ROOT_PASSWORD' => false,
            'MYSQL_TEST_LOGIN_FILE' => false,
            'MYSQL_USER' => false,
        ];

        return match ($driver) {
            'mysql' => [
                ...$environment,
                // MySQL 8.0 always reads .mylogin.cnf, even with --defaults-file.
                // Its documented test-login override safely points at a private absent file.
                'MYSQL_TEST_LOGIN_FILE' => $loginFile,
            ],
            'mariadb' => $environment,
            default => throw new RuntimeException("Database client environment is unsupported for {$driver}."),
        };
    }
}
