<?php

declare(strict_types=1);

namespace App\Services\FattureInCloud;

use App\Settings\FattureInCloudSettings;

final readonly class FattureInCloudConfiguration
{
    public const BASE_URL = 'https://api-v2.fattureincloud.it';

    public const SCOPES = 'entity.clients:r entity.clients:a products:r settings:r issued_documents.quotes:r issued_documents.quotes:a';

    public const SCOPE_VERSION = 1;

    public function __construct(private FattureInCloudSettings $settings) {}

    public function clientId(): ?string
    {
        return $this->normalized($this->settings->client_id);
    }

    public function clientSecret(): ?string
    {
        return $this->normalized($this->settings->encrypted_client_secret);
    }

    public function callbackUri(): string
    {
        return route('fatture-in-cloud.oauth.callback');
    }

    public function isComplete(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    private function normalized(?string $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
