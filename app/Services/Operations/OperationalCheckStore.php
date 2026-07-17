<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Data\Operations\OperationalCheckResult;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;
use stdClass;

final readonly class OperationalCheckStore
{
    public function __construct(private Filesystem $files) {}

    public function find(OperationalCheckType $type): ?OperationalCheckResult
    {
        $path = $this->path($type);

        if (! $this->files->exists($path)) {
            return null;
        }

        $contents = $this->files->get($path);

        try {
            $payload = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Operational status is not valid JSON: {$path}", previous: $exception);
        }

        if (! $payload instanceof stdClass) {
            throw new RuntimeException("Operational status has an invalid structure: {$path}");
        }

        $storedType = is_string($payload->type ?? null)
            ? OperationalCheckType::tryFrom($payload->type)
            : null;
        $status = is_string($payload->status ?? null)
            ? OperationalCheckStatus::tryFrom($payload->status)
            : null;

        if ($storedType !== $type || $status === null) {
            throw new RuntimeException("Operational status has invalid type or status values: {$path}");
        }

        return new OperationalCheckResult(
            type: $storedType,
            status: $status,
            lastAttemptedAt: $this->requiredDate($payload, 'last_attempted_at', $path),
            lastSucceededAt: $this->optionalDate($payload, 'last_succeeded_at', $path),
            lastFailedAt: $this->optionalDate($payload, 'last_failed_at', $path),
            errorText: $this->optionalString($payload, 'error_text', $path),
        );
    }

    public function put(
        OperationalCheckType $type,
        OperationalCheckStatus $status,
        CarbonImmutable $attemptedAt,
        ?string $error,
    ): OperationalCheckResult {
        $current = $this->find($type);
        $attemptedAt = $attemptedAt->utc();
        $result = new OperationalCheckResult(
            type: $type,
            status: $status,
            lastAttemptedAt: $attemptedAt,
            lastSucceededAt: $status === OperationalCheckStatus::Succeeded
                ? $attemptedAt
                : $current?->lastSucceededAt,
            lastFailedAt: $status === OperationalCheckStatus::Failed
                ? $attemptedAt
                : $current?->lastFailedAt,
            errorText: $status === OperationalCheckStatus::Failed
                ? mb_substr(trim((string) $error), 0, 20_000)
                : null,
        );
        $path = $this->path($type);
        $this->files->ensureDirectoryExists(dirname($path), 0700, true);

        try {
            $contents = json_encode([
                'type' => $result->type->value,
                'status' => $result->status->value,
                'last_attempted_at' => $result->lastAttemptedAt->toIso8601String(),
                'last_succeeded_at' => $result->lastSucceededAt?->toIso8601String(),
                'last_failed_at' => $result->lastFailedAt?->toIso8601String(),
                'error_text' => $result->errorText,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Operational status could not be encoded.', previous: $exception);
        }

        $this->files->replace($path, $contents, 0600);
        $storedHash = hash_file('sha256', $path);

        if ($storedHash === false || ! hash_equals(hash('sha256', $contents), $storedHash)) {
            throw new RuntimeException("Operational status could not be persisted atomically: {$path}");
        }

        return $result;
    }

    private function path(OperationalCheckType $type): string
    {
        $root = rtrim((string) config('assestme.backup.private_storage_path'), '/\\');

        if ($root === '' || ! str_starts_with($root, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Operational status requires an absolute private-storage path.');
        }

        return $root.DIRECTORY_SEPARATOR.'operational-status'.DIRECTORY_SEPARATOR.$type->value.'.json';
    }

    private function requiredDate(stdClass $payload, string $key, string $path): CarbonImmutable
    {
        $value = $payload->{$key} ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Operational status date {$key} is invalid: {$path}");
        }

        return CarbonImmutable::parse($value)->utc();
    }

    private function optionalDate(stdClass $payload, string $key, string $path): ?CarbonImmutable
    {
        $value = $payload->{$key} ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Operational status date {$key} is invalid: {$path}");
        }

        return CarbonImmutable::parse($value)->utc();
    }

    private function optionalString(stdClass $payload, string $key, string $path): ?string
    {
        $value = $payload->{$key} ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new RuntimeException("Operational status value {$key} is invalid: {$path}");
        }

        return $value;
    }
}
