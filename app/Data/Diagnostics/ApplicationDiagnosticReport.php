<?php

declare(strict_types=1);

namespace App\Data\Diagnostics;

final readonly class ApplicationDiagnosticReport
{
    /**
     * @param  list<DiagnosticCheckData>  $checks
     * @param  list<string>  $missingExtensions
     */
    public function __construct(
        public string $phpVersion,
        public string $memoryLimit,
        public array $missingExtensions,
        public string $driver,
        public ?string $product,
        public ?string $serverVersion,
        public array $checks,
    ) {}

    public function ok(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === DiagnosticCheckStatus::Failed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     ok: bool,
     *     runtime: array{php: string, memory_limit: string, missing_extensions: list<string>},
     *     database: array{driver: string, product: string|null, server_version: string|null},
     *     checks: array<string, array{group: string, status: string, detail: string}>
     * }
     */
    public function toArray(): array
    {
        $checks = [];

        foreach ($this->checks as $check) {
            $checks[$check->key] = $check->toArray();
        }

        return [
            'ok' => $this->ok(),
            'runtime' => [
                'php' => $this->phpVersion,
                'memory_limit' => $this->memoryLimit,
                'missing_extensions' => $this->missingExtensions,
            ],
            'database' => [
                'driver' => $this->driver,
                'product' => $this->product,
                'server_version' => $this->serverVersion,
            ],
            'checks' => $checks,
        ];
    }
}
