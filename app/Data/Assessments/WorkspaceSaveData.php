<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use JsonException;

final readonly class WorkspaceSaveData
{
    /**
     * @param array{
     *     assessment: array<string, mixed>,
     *     findings: list<array<string, mixed>>
     * } $payload
     */
    public function __construct(
        public string $requestId,
        public int $expectedVersion,
        public string $tabId,
        public array $payload,
        public string $payloadSha256,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function hashPayload(array $payload): string
    {
        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new JsonException('The workspace payload cannot be encoded.', previous: $exception);
        }

        return hash('sha256', $json);
    }
}
