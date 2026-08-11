<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudDocumentItemData
{
    public function __construct(
        public ?string $productId,
        public ?string $code,
        public string $name,
        public string $description,
        public float $quantity,
        public ?string $measure,
        public float $netPrice,
        public float $discount,
        public ?string $vatTypeId,
    ) {}
}
