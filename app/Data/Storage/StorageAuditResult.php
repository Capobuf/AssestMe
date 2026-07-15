<?php

declare(strict_types=1);

namespace App\Data\Storage;

final readonly class StorageAuditResult
{
    /**
     * @param  list<string>  $orphanFiles
     * @param  list<string>  $missingFiles
     * @param  list<string>  $hashMismatches
     * @param  list<string>  $unfinishedOperations
     */
    public function __construct(
        public array $orphanFiles,
        public array $missingFiles,
        public array $hashMismatches,
        public array $unfinishedOperations,
    ) {}

    public function hasIssues(): bool
    {
        return $this->orphanFiles !== []
            || $this->missingFiles !== []
            || $this->hashMismatches !== []
            || $this->unfinishedOperations !== [];
    }
}
