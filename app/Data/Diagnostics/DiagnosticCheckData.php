<?php

declare(strict_types=1);

namespace App\Data\Diagnostics;

final readonly class DiagnosticCheckData
{
    public function __construct(
        public string $key,
        public string $group,
        public DiagnosticCheckStatus $status,
        public string $detail,
    ) {}

    /** @return array{group: string, status: string, detail: string} */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'status' => $this->status->value,
            'detail' => $this->detail,
        ];
    }
}
