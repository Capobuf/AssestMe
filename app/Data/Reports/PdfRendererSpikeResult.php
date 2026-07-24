<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class PdfRendererSpikeResult
{
    public function __construct(
        public string $path,
        public float $generationSeconds,
        public int $sizeBytes,
        public string $sha256,
    ) {}
}
