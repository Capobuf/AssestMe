<?php

declare(strict_types=1);

namespace App\Data\GoogleDrive;

final readonly class GoogleDriveSyncResult
{
    public function __construct(
        public int $evaluated = 0,
        public int $skipped = 0,
        public int $synchronized = 0,
        public int $failed = 0,
        public bool $lockUnavailable = false,
        public bool $integrationUnavailable = false,
    ) {}

    public function exitCode(): int
    {
        if ($this->lockUnavailable) {
            return 2;
        }

        return ($this->failed > 0 || $this->integrationUnavailable) ? 1 : 0;
    }
}
