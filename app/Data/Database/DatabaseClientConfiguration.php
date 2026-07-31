<?php

declare(strict_types=1);

namespace App\Data\Database;

use SensitiveParameter;

final readonly class DatabaseClientConfiguration
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        #[SensitiveParameter]
        public string $password,
        public string $socket,
    ) {}
}
