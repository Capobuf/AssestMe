<?php

declare(strict_types=1);

namespace App\Data\Database;

final readonly class DatabaseIntegrityResult
{
    /**
     * @param  list<string>  $checkedTables
     * @param  array<string, bool|int|string>  $details
     */
    public function __construct(
        public bool $healthy,
        public string $driver,
        public string $product,
        public string $serverVersion,
        public string $database,
        public array $checkedTables,
        public array $details,
    ) {}
}
