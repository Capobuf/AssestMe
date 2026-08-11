<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Settings\GoogleDriveSettings;

final readonly class GoogleDriveConfiguration
{
    public function __construct(private GoogleDriveSettings $settings) {}

    public function clientId(): ?string
    {
        return $this->effective($this->settings->client_id, config('services.google.client_id'));
    }

    public function clientSecret(): ?string
    {
        return $this->effective(
            $this->settings->encrypted_client_secret,
            config('services.google.client_secret'),
        );
    }

    public function callbackUri(): string
    {
        return route('google-drive.oauth.callback');
    }

    public function isComplete(): bool
    {
        return $this->clientId() !== null
            && $this->clientSecret() !== null;
    }

    public function applyToRuntimeConfig(): void
    {
        config()->set('services.google.client_id', $this->clientId());
        config()->set('services.google.client_secret', $this->clientSecret());
        config()->set('services.google.redirect', $this->callbackUri());
    }

    private function effective(?string $stored, mixed $fallback): ?string
    {
        if (is_string($stored) && trim($stored) !== '') {
            return trim($stored);
        }

        return is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
    }
}
