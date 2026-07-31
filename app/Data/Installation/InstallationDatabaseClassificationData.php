<?php

declare(strict_types=1);

namespace App\Data\Installation;

final readonly class InstallationDatabaseClassificationData
{
    /**
     * @param  list<string>  $tables
     */
    public function __construct(
        public InstallationDatabaseStatus $status,
        public array $tables,
        public ?string $markerInstallationId = null,
    ) {}
}
