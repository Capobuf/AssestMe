<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use JsonException;

final readonly class ReorderFindingsData
{
    /** @param list<int> $orderedFindingIds */
    public function __construct(
        public string $requestId,
        public int $expectedVersion,
        public array $orderedFindingIds,
        public string $payloadSha256,
    ) {}

    /** @param list<int> $orderedFindingIds */
    public static function hashPayload(array $orderedFindingIds): string
    {
        try {
            $json = json_encode(
                ['ordered_finding_ids' => $orderedFindingIds],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException $exception) {
            throw new JsonException('The reorder payload cannot be encoded.', previous: $exception);
        }

        return hash('sha256', $json);
    }
}
