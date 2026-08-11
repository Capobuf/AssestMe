<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Data\GoogleDrive\GoogleAccessTokenData;
use App\Settings\GoogleDriveSettings;
use Google\Client;
use RuntimeException;
use Throwable;

final readonly class GoogleDriveTokenService
{
    private const DRIVE_FILE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function __construct(
        private Client $client,
        private GoogleDriveSettings $settings,
        private GoogleDriveConfiguration $configuration,
    ) {}

    public function isInstallationConfigured(): bool
    {
        return $this->configuration->isComplete();
    }

    public function accessToken(): GoogleAccessTokenData
    {
        if (! $this->isInstallationConfigured()) {
            throw new RuntimeException(__('assestme.google_drive.states.installation_unavailable'));
        }

        $refreshToken = $this->settings->encrypted_refresh_token;
        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException(__('assestme.google_drive.errors.not_connected'));
        }

        $this->configureClient();
        try {
            $response = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.authorization_revoked'));
        }
        $token = $response['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException(__('assestme.google_drive.errors.authorization_revoked'));
        }

        $expiresIn = $response['expires_in'] ?? 3600;

        return new GoogleAccessTokenData(
            token: $token,
            expiresIn: is_numeric($expiresIn) ? max(1, (int) $expiresIn) : 3600,
        );
    }

    public function revoke(): bool
    {
        $refreshToken = $this->settings->encrypted_refresh_token;
        if (! is_string($refreshToken) || $refreshToken === '' || ! $this->isInstallationConfigured()) {
            return true;
        }

        $this->configureClient();

        try {
            return $this->client->revokeToken($refreshToken);
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.revocation_failed'));
        }
    }

    private function configureClient(): void
    {
        $this->client->setClientId((string) $this->configuration->clientId());
        $this->client->setClientSecret((string) $this->configuration->clientSecret());
        $this->client->setScopes(['openid', 'email', self::DRIVE_FILE_SCOPE]);
    }
}
