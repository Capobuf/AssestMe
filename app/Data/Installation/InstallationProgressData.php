<?php

declare(strict_types=1);

namespace App\Data\Installation;

final readonly class InstallationProgressData
{
    public function __construct(
        public string $installationId,
        public string $step,
        public ?ApplicationConfigurationData $application = null,
        public ?DatabaseConfigurationData $database = null,
    ) {}

    /**
     * @return array{
     *   schema_version: int,
     *   installation_id: string,
     *   step: string,
     *   application: array{name: string, url: string, timezone: string, locale: string, backup_root: string, weasyprint_binary: string, php_binary: string}|null,
     *   database: array{driver: string, database: string, host: string, port: int, username: string, password: string, socket: string, charset: string, collation: string}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'installation_id' => $this->installationId,
            'step' => $this->step,
            'application' => $this->application?->toArray(),
            'database' => $this->database?->toArray(),
        ];
    }
}
