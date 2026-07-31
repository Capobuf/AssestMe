<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Data\Database\DatabaseClientConfiguration;
use Illuminate\Database\Connection;
use RuntimeException;

final class DatabaseClientConfigurationResolver
{
    public function resolve(Connection $connection): DatabaseClientConfiguration
    {
        $host = $connection->getConfig('host');
        $port = $connection->getConfig('port');
        $database = $connection->getConfig('database');
        $username = $connection->getConfig('username');
        $password = $connection->getConfig('password');
        $socket = $connection->getConfig('unix_socket');

        if (! is_string($host)
            || (! is_int($port) && (! is_string($port) || preg_match('/^[0-9]+$/D', $port) !== 1))
            || ! is_string($database)
            || $database === ''
            || str_starts_with($database, '-')
            || preg_match('/[\x00-\x1F\x7F]/', $database) === 1
            || ! is_string($username)
            || $username === ''
            || ! is_string($password)
            || ! is_string($socket)
            || ($host === '' && $socket === '')) {
            throw new RuntimeException('Database client connection configuration is invalid.');
        }

        $normalizedPort = (int) $port;

        if ($normalizedPort < 1 || $normalizedPort > 65535) {
            throw new RuntimeException('Database client connection port is invalid.');
        }

        return new DatabaseClientConfiguration(
            host: $host,
            port: $normalizedPort,
            database: $database,
            username: $username,
            password: $password,
            socket: $socket,
        );
    }
}
