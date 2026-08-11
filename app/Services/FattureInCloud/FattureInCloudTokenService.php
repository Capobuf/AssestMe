<?php

declare(strict_types=1);

namespace App\Services\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudTokenData;
use App\Settings\FattureInCloudSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class FattureInCloudTokenService
{
    public function __construct(
        private FattureInCloudSettings $settings,
        private FattureInCloudConfiguration $configuration,
    ) {}

    public function exchangeAuthorizationCode(string $code): FattureInCloudTokenData
    {
        if (! $this->configuration->isComplete()) {
            throw FattureInCloudException::authorizationRequired();
        }

        $token = $this->requestToken([
            'grant_type' => 'authorization_code',
            'client_id' => $this->configuration->clientId(),
            'client_secret' => $this->configuration->clientSecret(),
            'redirect_uri' => $this->configuration->callbackUri(),
            'code' => $code,
        ]);
        $this->store($token);

        return $token;
    }

    public function accessToken(): string
    {
        $token = $this->settings->encrypted_access_token;
        $expiresAt = $this->expiry();
        if (is_string($token) && $token !== '' && $expiresAt?->isAfter(Carbon::now('UTC')->addMinute())) {
            return $token;
        }

        return $this->refreshAccessToken();
    }

    public function refreshAccessToken(): string
    {
        $refreshToken = $this->settings->encrypted_refresh_token;
        if (! $this->configuration->isComplete() || ! is_string($refreshToken) || $refreshToken === '') {
            throw FattureInCloudException::authorizationRequired();
        }

        $token = $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => $this->configuration->clientId(),
            'client_secret' => $this->configuration->clientSecret(),
            'refresh_token' => $refreshToken,
        ]);
        $this->store($token);

        return $token->accessToken;
    }

    public function clearConnection(): void
    {
        $this->settings->encrypted_access_token = null;
        $this->settings->access_token_expires_at = null;
        $this->settings->encrypted_refresh_token = null;
        $this->settings->company_id = null;
        $this->settings->company_name = null;
        $this->settings->default_vat_type_id = null;
        $this->settings->default_vat_type_label = null;
        $this->settings->scope_version = null;
        $this->settings->save();
    }

    /** @param array<string, string|null> $payload */
    private function requestToken(array $payload): FattureInCloudTokenData
    {
        $grantType = $payload['grant_type'] === 'refresh_token'
            ? 'refresh_token'
            : 'authorization_code';

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post(FattureInCloudConfiguration::BASE_URL.'/oauth/token', $payload);
        } catch (ConnectionException) {
            Log::warning('Fatture in Cloud token request could not connect.', [
                'grant_type' => $grantType,
            ]);

            throw FattureInCloudException::providerUnavailable();
        }

        if (! $response->successful()) {
            Log::warning('Fatture in Cloud token request was rejected.', [
                'grant_type' => $grantType,
                'status' => $response->status(),
            ]);

            throw $response->status() === 401
                ? FattureInCloudException::authorizationRequired()
                : FattureInCloudException::providerUnavailable();
        }

        return $this->parse($response);
    }

    private function parse(Response $response): FattureInCloudTokenData
    {
        $body = $response->json();
        if (! is_array($body)) {
            throw FattureInCloudException::invalidResponse();
        }

        $accessToken = $body['access_token'] ?? null;
        $refreshToken = $body['refresh_token'] ?? null;
        $expiresIn = $body['expires_in'] ?? null;
        if (! is_string($accessToken) || $accessToken === ''
            || ! is_string($refreshToken) || $refreshToken === ''
            || ! is_numeric($expiresIn) || (int) $expiresIn < 1) {
            throw FattureInCloudException::invalidResponse();
        }

        return new FattureInCloudTokenData($accessToken, $refreshToken, (int) $expiresIn);
    }

    private function store(FattureInCloudTokenData $token): void
    {
        $this->settings->encrypted_access_token = $token->accessToken;
        $this->settings->encrypted_refresh_token = $token->refreshToken;
        $this->settings->access_token_expires_at = Carbon::now('UTC')
            ->addSeconds($token->expiresIn)
            ->toIso8601String();
        $this->settings->save();
    }

    private function expiry(): ?Carbon
    {
        $value = $this->settings->access_token_expires_at;
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
