<?php

declare(strict_types=1);

namespace App\Data\Installation;

final readonly class InstallationRuntimeInspectionResult
{
    /**
     * @param  list<InstallationRequirementResult>  $requirements
     */
    public function __construct(
        private array $requirements,
        public ?string $phpBinary,
        public ?string $weasyPrintBinary,
    ) {}

    /** @return list<InstallationRequirementResult> */
    public function requirements(): array
    {
        return $this->requirements;
    }

    /** @return list<InstallationRequirementResult> */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->requirements,
            static fn (InstallationRequirementResult $requirement): bool => ! $requirement->passed,
        ));
    }

    public function passed(): bool
    {
        return $this->failures() === [];
    }

    public function requirement(string $key): ?InstallationRequirementResult
    {
        foreach ($this->requirements as $requirement) {
            if ($requirement->key === $key) {
                return $requirement;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     passed: bool,
     *     php_binary: string|null,
     *     weasyprint_binary: string|null,
     *     requirements: list<array{
     *         key: string,
     *         group: string,
     *         passed: bool,
     *         expected: string,
     *         actual: string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed(),
            'php_binary' => $this->phpBinary,
            'weasyprint_binary' => $this->weasyPrintBinary,
            'requirements' => array_map(
                static fn (InstallationRequirementResult $requirement): array => $requirement->toArray(),
                $this->requirements,
            ),
        ];
    }
}
