<?php

declare(strict_types=1);

namespace App\Data\Installation;

use App\Enums\SupportedDatabaseDriver;

final readonly class DatabaseServerIdentityData
{
    public function __construct(
        public SupportedDatabaseDriver $driver,
        public string $product,
        public string $version,
        public string $versionComment,
    ) {}
}
