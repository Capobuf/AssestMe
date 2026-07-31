<?php

declare(strict_types=1);

namespace App\Data\Installation;

use App\Enums\SupportedDatabaseDriver;

final readonly class DatabaseCapabilityProbeResultData
{
    /**
     * @param  list<string>  $checks
     */
    public function __construct(
        public SupportedDatabaseDriver $driver,
        public string $product,
        public string $serverVersion,
        public string $charset,
        public string $collation,
        public array $checks,
    ) {}
}
