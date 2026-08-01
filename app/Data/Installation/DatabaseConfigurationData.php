<?php

declare(strict_types=1);

namespace App\Data\Installation;

use App\Enums\SupportedDatabaseDriver;
use SensitiveParameter;

final readonly class DatabaseConfigurationData
{
    public function __construct(
        public SupportedDatabaseDriver $driver,
        public string $database,
        public string $host = '127.0.0.1',
        public int $port = 3306,
        public string $username = '',
        #[SensitiveParameter]
        public string $password = '',
        public string $socket = '',
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci',
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function toLaravelConfig(): array
    {
        if ($this->driver === SupportedDatabaseDriver::Sqlite) {
            return [
                'driver' => 'sqlite',
                'url' => null,
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => true,
                'busy_timeout' => 5000,
                'journal_mode' => 'WAL',
                'synchronous' => 'NORMAL',
                'transaction_mode' => 'IMMEDIATE',
            ];
        }

        return [
            'driver' => $this->driver->value,
            'url' => null,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'unix_socket' => $this->socket,
            'charset' => $this->charset,
            'collation' => $this->collation,
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => 'InnoDB',
        ];
    }

    /** @return array{driver: string, database: string, host: string, port: int, username: string, password: string, socket: string, charset: string, collation: string} */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver->value,
            'database' => $this->database,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'socket' => $this->socket,
            'charset' => $this->charset,
            'collation' => $this->collation,
        ];
    }

    /** @param array{driver: string, database: string, host: string, port: int, username: string, password: string, socket: string, charset: string, collation: string, dump_binary?: string, restore_binary?: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            driver: SupportedDatabaseDriver::from($data['driver']),
            database: $data['database'],
            host: $data['host'],
            port: $data['port'],
            username: $data['username'],
            password: $data['password'],
            socket: $data['socket'],
            charset: $data['charset'],
            collation: $data['collation'],
        );
    }
}
