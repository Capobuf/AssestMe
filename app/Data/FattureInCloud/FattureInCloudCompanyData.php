<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudCompanyData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
