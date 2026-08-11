<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudProductData
{
    public function __construct(
        public string $id,
        public ?string $code,
        public string $name,
        public ?string $description,
        public ?string $measure,
        public ?float $netPrice,
        public ?string $vatTypeId,
    ) {}
}
