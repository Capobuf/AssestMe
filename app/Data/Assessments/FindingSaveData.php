<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Evidence\PendingEvidenceFileData;
use App\Data\Evidence\PendingEvidenceUrlData;
use JsonException;

final readonly class FindingSaveData
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<PendingEvidenceFileData>  $evidenceFiles
     */
    public function __construct(
        public string $requestId,
        public int $expectedVersion,
        public string $tabId,
        public array $payload,
        public string $payloadSha256,
        public array $evidenceFiles = [],
        public ?PendingEvidenceUrlData $evidenceUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $findingPayload
     * @param  list<PendingEvidenceFileData>  $evidenceFiles
     * @return array<string, mixed>
     */
    public static function aggregatePayload(
        array $findingPayload,
        array $evidenceFiles,
        ?PendingEvidenceUrlData $evidenceUrl,
    ): array {
        return [
            ...$findingPayload,
            '_evidence' => [
                'files' => array_map(
                    static fn (PendingEvidenceFileData $file): array => $file->normalizedPayload(),
                    $evidenceFiles,
                ),
                'url' => $evidenceUrl?->normalizedPayload(),
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function hashPayload(array $payload): string
    {
        try {
            $json = json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new JsonException('The finding payload cannot be encoded.', previous: $exception);
        }

        return hash('sha256', $json);
    }
}
