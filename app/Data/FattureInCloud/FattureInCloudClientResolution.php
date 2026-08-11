<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudClientResolution
{
    /** @param list<FattureInCloudClientData> $candidates */
    public function __construct(
        public ?FattureInCloudClientData $client,
        public array $candidates = [],
    ) {}

    public function resolved(): bool
    {
        return $this->client instanceof FattureInCloudClientData;
    }
}
