<?php

declare(strict_types=1);

namespace App\Data\Installation;

final readonly class InstallationFinalCheckResult
{
    /** @param list<array{key: string, label: string, status: 'passed'|'failed'|'pending', detail: string}> $checks */
    public function __construct(public array $checks) {}

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === 'failed') {
                return false;
            }
        }

        return true;
    }
}
