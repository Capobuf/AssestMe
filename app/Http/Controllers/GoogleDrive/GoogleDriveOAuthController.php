<?php

declare(strict_types=1);

namespace App\Http\Controllers\GoogleDrive;

use App\Filament\Pages\GoogleDriveSettingsPage;
use App\Http\Controllers\Controller;
use App\Services\GoogleDrive\GoogleDriveConfiguration;
use App\Services\GoogleDrive\GoogleDriveTokenService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;
use Throwable;

final class GoogleDriveOAuthController extends Controller
{
    private const DRIVE_FILE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function redirect(
        GoogleDriveTokenService $tokens,
        GoogleDriveConfiguration $configuration,
    ): RedirectResponse {
        if (! $tokens->isInstallationConfigured()) {
            Notification::make()
                ->danger()
                ->title(__('assestme.google_drive.notifications.installation_unavailable'))
                ->body(__('assestme.google_drive.notifications.installation_unavailable_body'))
                ->send();

            return $this->settingsRedirect();
        }

        try {
            $configuration->applyToRuntimeConfig();
            $provider = Socialite::driver('google');
            if (! $provider instanceof AbstractProvider) {
                throw new RuntimeException(__('assestme.google_drive.errors.oauth_provider'));
            }

            return $provider->setScopes(['openid', 'email', self::DRIVE_FILE_SCOPE])
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                    'include_granted_scopes' => 'false',
                ])
                ->redirect();
        } catch (Throwable) {
            Notification::make()
                ->danger()
                ->title(__('assestme.google_drive.notifications.oauth_failed'))
                ->body(__('assestme.google_drive.notifications.oauth_failed_body'))
                ->send();

            return $this->settingsRedirect();
        }
    }

    public function callback(
        GoogleDriveSettings $settings,
        GoogleDriveConfiguration $configuration,
        GoogleWorkspaceClient $google,
    ): RedirectResponse {
        try {
            if (! $configuration->isComplete()) {
                throw new RuntimeException('Incomplete Google application configuration.');
            }
            $configuration->applyToRuntimeConfig();
            $user = Socialite::driver('google')->user();
            if (! $user instanceof SocialiteUser) {
                throw new RuntimeException('Invalid Google identity response.');
            }

            $email = $user->getEmail();
            $refreshToken = data_get($user, 'refreshToken');
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('Missing Google account email.');
            }
            if (! is_string($refreshToken) || $refreshToken === '') {
                throw new RuntimeException('Missing Google refresh token.');
            }

            $settings->google_account_email = mb_strtolower($email);
            $settings->encrypted_refresh_token = $refreshToken;
            $settings->root_folder_id = null;
            $settings->root_folder_name = null;
            $settings->sync_enabled = false;
            $settings->save();
        } catch (Throwable) {
            Notification::make()
                ->danger()
                ->title(__('assestme.google_drive.notifications.oauth_failed'))
                ->body(__('assestme.google_drive.notifications.oauth_failed_body'))
                ->send();

            return $this->settingsRedirect();
        }

        try {
            $root = $google->createApplicationRoot();
            $settings->root_folder_id = $root->id;
            $settings->root_folder_name = $root->name;
            $settings->sync_enabled = true;
            $settings->save();

            Notification::make()
                ->success()
                ->title(__('assestme.google_drive.notifications.connected'))
                ->body(__('assestme.google_drive.notifications.connected_body'))
                ->send();
        } catch (Throwable) {
            Notification::make()
                ->danger()
                ->title(__('assestme.google_drive.notifications.root_creation_failed'))
                ->body(__('assestme.google_drive.notifications.root_creation_failed_body'))
                ->send();
        }

        return $this->settingsRedirect();
    }

    private function settingsRedirect(): RedirectResponse
    {
        return redirect(GoogleDriveSettingsPage::getUrl(isAbsolute: false));
    }
}
