<?php

declare(strict_types=1);

namespace App\Data\Installation;

final readonly class InstallationRequirementResult
{
    public function __construct(
        public string $key,
        public string $group,
        public bool $passed,
        public string $expected,
        public string $actual,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     group: string,
     *     passed: bool,
     *     expected: string,
     *     actual: string
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'group' => $this->group,
            'passed' => $this->passed,
            'expected' => $this->expected,
            'actual' => $this->actual,
        ];
    }
}
