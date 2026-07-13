<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class WorkspaceSaveResult
{
    /** @param array<string, int> $idMap */
    public function __construct(
        public int $appliedVersion,
        public array $idMap,
        public bool $idempotentReplay = false,
    ) {}

    /** @return array{applied_version: int, id_map: array<string, int>} */
    public function toArray(): array
    {
        return [
            'applied_version' => $this->appliedVersion,
            'id_map' => $this->idMap,
        ];
    }

    /** @param array{applied_version: int, id_map: array<string, int>} $response */
    public static function fromStoredResponse(array $response): self
    {
        return new self(
            appliedVersion: $response['applied_version'],
            idMap: $response['id_map'],
            idempotentReplay: true,
        );
    }
}
