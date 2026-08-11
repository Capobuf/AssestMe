<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudTokenData
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}
}
