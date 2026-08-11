<?php

declare(strict_types=1);

namespace App\Services\FattureInCloud;

use RuntimeException;

final class FattureInCloudException extends RuntimeException
{
    /** @var list<string> */
    public readonly array $validationFields;

    /** @param list<string> $validationFields */
    private function __construct(
        string $message,
        public readonly string $reason,
        array $validationFields = [],
    ) {
        parent::__construct($message);
        $this->validationFields = $validationFields;
    }

    public static function authorizationRequired(): self
    {
        return new self(__('assestme.fatture_in_cloud.errors.authorization_required'), 'authorization_required');
    }

    public static function providerUnavailable(): self
    {
        return new self(__('assestme.fatture_in_cloud.errors.provider_unavailable'), 'provider_unavailable');
    }

    /** @param list<string> $validationFields */
    public static function requestRejected(array $validationFields = []): self
    {
        return new self(
            __('assestme.fatture_in_cloud.errors.request_rejected'),
            'request_rejected',
            $validationFields,
        );
    }

    public static function invalidResponse(): self
    {
        return new self(__('assestme.fatture_in_cloud.errors.invalid_response'), 'invalid_response');
    }

    public static function missingPermission(): self
    {
        return new self(__('assestme.fatture_in_cloud.errors.missing_permission'), 'missing_permission');
    }

    public static function rateLimited(?string $retryAfter): self
    {
        $delay = self::boundedRetryAfter($retryAfter);

        return new self($delay === null
            ? __('assestme.fatture_in_cloud.errors.rate_limited')
            : __('assestme.fatture_in_cloud.errors.rate_limited_retry', ['delay' => $delay]), 'rate_limited');
    }

    private static function boundedRetryAfter(?string $value): ?string
    {
        if ($value === null || preg_match('/\A[0-9]{1,5}\z/', $value) !== 1) {
            return null;
        }

        $seconds = (int) $value;

        return $seconds <= 86400 ? $seconds.' secondi' : null;
    }
}
