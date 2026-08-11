<?php

declare(strict_types=1);

namespace App\Data\GoogleDrive;

final readonly class GoogleAccessTokenData
{
    public function __construct(
        public string $token,
        public int $expiresIn,
    ) {}
}
