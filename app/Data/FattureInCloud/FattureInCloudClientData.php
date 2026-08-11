<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudClientData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $vatNumber,
        public ?string $taxCode,
    ) {}
}
