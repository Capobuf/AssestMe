<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\IntegrationsCluster;
use App\Filament\Pages\Concerns\HasParentSettingsNavigation;
use App\Models\AssessmentGoogleDriveSync;
use App\Services\GoogleDrive\GoogleDriveConfiguration;
use App\Services\GoogleDrive\GoogleDriveSyncService;
use App\Services\GoogleDrive\GoogleDriveTokenService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;

final class GoogleDriveSettingsPage extends SettingsPage
{
    use HasParentSettingsNavigation;

    protected static ?string $cluster = IntegrationsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static ?int $navigationSort = 2;

    protected static string $settings = GoogleDriveSettings::class;

    protected string $view = 'filament.pages.google-drive-settings-page';

    private bool $connectionResetAfterConfigurationSave = false;

    public static function getNavigationLabel(): string
    {
        return __('assestme.google_drive.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.google_drive.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.google_drive.guide.heading'))
                ->description(__('assestme.google_drive.guide.description'))
                ->schema([
                    Callout::make(__('assestme.google_drive.guide.steps.project.heading'))
                        ->description(__('assestme.google_drive.guide.steps.project.description'))
                        ->info(),
                    Callout::make(__('assestme.google_drive.guide.steps.apis.heading'))
                        ->description(__('assestme.google_drive.guide.steps.apis.description'))
                        ->actions([
                            Action::make('google_workspace_apis')
                                ->label(__('assestme.google_drive.guide.links.enable_apis'))
                                ->url('https://developers.google.com/workspace/guides/enable-apis')
                                ->openUrlInNewTab(),
                        ]),
                    Callout::make(__('assestme.google_drive.guide.steps.auth.heading'))
                        ->description(__('assestme.google_drive.guide.steps.auth.description'))
                        ->warning()
                        ->actions([
                            Action::make('google_auth_branding')
                                ->label(__('assestme.google_drive.guide.links.branding'))
                                ->url('https://support.google.com/cloud/answer/15549049')
                                ->openUrlInNewTab(),
                            Action::make('google_auth_audience')
                                ->label(__('assestme.google_drive.guide.links.audience'))
                                ->url('https://support.google.com/cloud/answer/15549945')
                                ->openUrlInNewTab(),
                            Action::make('google_auth_data_access')
                                ->label(__('assestme.google_drive.guide.links.data_access'))
                                ->url('https://support.google.com/cloud/answer/15549135')
                                ->openUrlInNewTab(),
                        ]),
                    Callout::make(__('assestme.google_drive.guide.steps.client.heading'))
                        ->description(__('assestme.google_drive.guide.steps.client.description'))
                        ->actions([
                            Action::make('google_oauth_web_server')
                                ->label(__('assestme.google_drive.guide.links.oauth_web'))
                                ->url('https://developers.google.com/identity/protocols/oauth2/web-server')
                                ->openUrlInNewTab(),
                        ]),
                    Callout::make(__('assestme.google_drive.guide.steps.callback.heading'))
                        ->description(__('assestme.google_drive.guide.steps.callback.description')),
                    Callout::make(__('assestme.google_drive.guide.steps.credentials.heading'))
                        ->description(__('assestme.google_drive.guide.steps.credentials.description')),
                ])
                ->collapsible()
                ->collapsed(fn (): bool => $this->installationConfigured())
                ->columnSpanFull(),
            Section::make(__('assestme.google_drive.configuration.heading'))
                ->description(__('assestme.google_drive.configuration.description'))
                ->schema([
                    TextInput::make('client_id')
                        ->label(__('assestme.google_drive.fields.client_id'))
                        ->helperText(__('assestme.google_drive.help.client_id'))
                        ->required()
                        ->extraInputAttributes(['dusk' => 'google-drive-client-id'])
                        ->maxLength(512),
                    TextInput::make('client_secret')
                        ->label(__('assestme.google_drive.fields.client_secret'))
                        ->helperText(fn (): string => $this->secretHelp($this->configuration()->clientSecret() !== null))
                        ->password()
                        ->autocomplete('new-password')
                        ->required(fn (): bool => $this->configuration()->clientSecret() === null)
                        ->extraInputAttributes(['dusk' => 'google-drive-client-secret'])
                        ->maxLength(2048),
                    TextInput::make('callback_uri')
                        ->label(__('assestme.google_drive.fields.callback_uri'))
                        ->helperText(__('assestme.google_drive.help.callback_uri'))
                        ->disabled()
                        ->dehydrated(false)
                        ->copyable(copyMessage: __('assestme.google_drive.actions.callback_copied'))
                        ->extraInputAttributes(['dusk' => 'google-drive-callback'])
                        ->maxLength(2048),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public function usage(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.google_drive.usage.heading'))
                ->description(__('assestme.google_drive.usage.description'))
                ->schema([
                    Callout::make(__('assestme.google_drive.usage.incomplete_heading'))
                        ->description(__('assestme.google_drive.states.installation_unavailable'))
                        ->warning()
                        ->visible(fn (): bool => ! $this->installationConfigured()),
                    Callout::make(__('assestme.google_drive.usage.disconnected_heading'))
                        ->description(__('assestme.google_drive.states.disconnected'))
                        ->info()
                        ->visible(fn (): bool => $this->installationConfigured() && ! $this->connected()),
                    Callout::make(__('assestme.google_drive.usage.root_pending_heading'))
                        ->description(__('assestme.google_drive.states.root_pending'))
                        ->warning()
                        ->visible(fn (): bool => $this->connected() && ! $this->configured()),
                    TextEntry::make('google_account')
                        ->label(__('assestme.google_drive.fields.account'))
                        ->state(fn (): ?string => $this->settings()->google_account_email)
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->visible(fn (): bool => $this->connected()),
                    TextEntry::make('managed_root')
                        ->label(__('assestme.google_drive.fields.root'))
                        ->state(fn (): string => $this->settings()->root_folder_name ?? __('assestme.google_drive.states.no_root'))
                        ->icon(Heroicon::OutlinedFolder)
                        ->visible(fn (): bool => $this->connected()),
                    TextEntry::make('automatic_sync')
                        ->label(__('assestme.google_drive.fields.automatic_sync'))
                        ->state(fn (): string => $this->settings()->sync_enabled
                            ? __('assestme.google_drive.states.enabled')
                            : __('assestme.google_drive.states.disabled'))
                        ->badge()
                        ->color(fn (): string => $this->settings()->sync_enabled ? 'success' : 'gray')
                        ->visible(fn (): bool => $this->configured()),
                    TextEntry::make('sync_status')
                        ->label(__('assestme.google_drive.fields.status'))
                        ->state(fn (): string => $this->latestError() !== null
                            ? __('assestme.google_drive.states.error')
                            : ($this->lastSync() !== null
                                ? __('assestme.google_drive.states.synchronized')
                                : __('assestme.google_drive.states.pending')))
                        ->badge()
                        ->color(fn (): string => $this->latestError() !== null ? 'danger' : 'primary')
                        ->visible(fn (): bool => $this->configured()),
                    TextEntry::make('last_sync')
                        ->label(__('assestme.google_drive.fields.last_sync'))
                        ->state(fn (): string => $this->lastSync() ?? __('assestme.google_drive.states.never'))
                        ->icon(Heroicon::OutlinedClock)
                        ->visible(fn (): bool => $this->configured()),
                    TextEntry::make('latest_error')
                        ->label(__('assestme.google_drive.fields.latest_error'))
                        ->state(fn (): ?string => $this->latestError())
                        ->color('danger')
                        ->visible(fn (): bool => $this->configured() && $this->latestError() !== null),
                    Actions::make([
                        Action::make('connect_google')
                            ->label(__('assestme.google_drive.actions.connect'))
                            ->icon(Heroicon::OutlinedLink)
                            ->url(fn (): string => route('google-drive.oauth.redirect'))
                            ->visible(fn (): bool => $this->installationConfigured() && ! $this->connected()),
                        Action::make('prepare_google_root')
                            ->label(__('assestme.google_drive.actions.prepare_root'))
                            ->icon(Heroicon::OutlinedFolderPlus)
                            ->action(function (): void {
                                $this->prepareRoot(app(GoogleWorkspaceClient::class));
                            })
                            ->visible(fn (): bool => $this->connected() && ! $this->configured()),
                        Action::make('sync_google_now')
                            ->label(__('assestme.google_drive.actions.sync_now'))
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->action(function (): void {
                                $this->syncNow(app(GoogleDriveSyncService::class));
                            })
                            ->visible(fn (): bool => $this->configured()),
                        Action::make('toggle_google_sync')
                            ->label(fn (): string => $this->settings()->sync_enabled
                                ? __('assestme.google_drive.actions.disable_automatic')
                                : __('assestme.google_drive.actions.enable_automatic'))
                            ->icon(Heroicon::OutlinedClock)
                            ->color('gray')
                            ->action(function (): void {
                                $this->toggleSync();
                            })
                            ->visible(fn (): bool => $this->configured()),
                        Action::make('verify_google_connection')
                            ->label(__('assestme.google_drive.actions.verify'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->color('gray')
                            ->action(function (): void {
                                $this->verifyConnection(app(GoogleWorkspaceClient::class));
                            })
                            ->visible(fn (): bool => $this->configured()),
                        Action::make('disconnect_google')
                            ->label(__('assestme.google_drive.actions.disconnect'))
                            ->icon(Heroicon::OutlinedLinkSlash)
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(function (): void {
                                $this->disconnect(app(GoogleDriveTokenService::class));
                            })
                            ->visible(fn (): bool => $this->connected()),
                    ])->columnSpanFull(),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $configuration = $this->configuration();

        return [
            'client_id' => $configuration->clientId(),
            'client_secret' => null,
            'callback_uri' => $configuration->callbackUri(),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settings = $this->settings();
        $configuration = $this->configuration();
        $clientId = $this->normalizedString($data['client_id'] ?? null);
        $clientSecret = $this->replacementSecret(
            $data['client_secret'] ?? null,
            $settings->encrypted_client_secret,
        );
        $this->connectionResetAfterConfigurationSave = $this->connected()
            && ($this->changed($configuration->clientId(), $clientId)
                || $this->changed($configuration->clientSecret(), $clientSecret ?? $configuration->clientSecret()));

        $values = [
            'client_id' => $clientId,
            'encrypted_client_secret' => $clientSecret,
        ];

        if ($this->connectionResetAfterConfigurationSave) {
            $values += [
                'sync_enabled' => false,
                'google_account_email' => null,
                'encrypted_refresh_token' => null,
                'root_folder_id' => null,
                'root_folder_name' => null,
            ];
        }

        return $values;
    }

    protected function afterSave(): void
    {
        $this->data['client_secret'] = null;
    }

    public function getSavedNotificationTitle(): string
    {
        return $this->connectionResetAfterConfigurationSave
            ? __('assestme.google_drive.notifications.configuration_saved_reconnect')
            : __('assestme.google_drive.notifications.configuration_saved');
    }

    public function installationConfigured(): bool
    {
        return app(GoogleDriveTokenService::class)->isInstallationConfigured();
    }

    public function settings(): GoogleDriveSettings
    {
        return app(GoogleDriveSettings::class);
    }

    public function connected(): bool
    {
        $settings = $this->settings();

        return is_string($settings->google_account_email)
            && $settings->google_account_email !== ''
            && is_string($settings->encrypted_refresh_token)
            && $settings->encrypted_refresh_token !== '';
    }

    public function configured(): bool
    {
        $settings = $this->settings();

        return $this->connected()
            && is_string($settings->root_folder_id)
            && $settings->root_folder_id !== '';
    }

    public function toggleSync(): void
    {
        $settings = $this->settings();
        if (! $this->configured()) {
            Notification::make()->danger()
                ->title(__('assestme.google_drive.errors.configuration_incomplete'))
                ->send();

            return;
        }

        $settings->sync_enabled = ! $settings->sync_enabled;
        $settings->save();

        Notification::make()->success()
            ->title($settings->sync_enabled
                ? __('assestme.google_drive.notifications.automatic_enabled')
                : __('assestme.google_drive.notifications.automatic_disabled'))
            ->send();
    }

    public function syncNow(GoogleDriveSyncService $sync): void
    {
        if (! $this->configured()) {
            Notification::make()->danger()
                ->title(__('assestme.google_drive.errors.configuration_incomplete'))
                ->send();

            return;
        }

        $result = $sync->syncAll(force: true, allowWhenDisabled: true);
        if ($result->lockUnavailable) {
            Notification::make()->warning()
                ->title(__('assestme.google_drive.notifications.sync_locked'))
                ->send();

            return;
        }

        Notification::make()
            ->title($result->failed === 0
                ? __('assestme.google_drive.notifications.sync_complete')
                : __('assestme.google_drive.notifications.sync_failed'))
            ->body(__('assestme.google_drive.command.result', [
                'evaluated' => $result->evaluated,
                'skipped' => $result->skipped,
                'synchronized' => $result->synchronized,
                'failed' => $result->failed,
            ]))
            ->status($result->failed === 0 ? 'success' : 'danger')
            ->send();
    }

    public function lastSync(): ?string
    {
        $last = AssessmentGoogleDriveSync::query()->whereNotNull('last_synced_at')->max('last_synced_at');

        return is_string($last)
            ? Carbon::parse($last, 'UTC')->timezone('Europe/Rome')->format('d/m/Y H:i')
            : null;
    }

    public function latestError(): ?string
    {
        $error = AssessmentGoogleDriveSync::query()
            ->whereNotNull('last_error')
            ->latest('last_error_at')
            ->value('last_error');

        return is_string($error) && $error !== '' ? $error : null;
    }

    public function verifyConnection(GoogleWorkspaceClient $google): void
    {
        $folderId = $this->settings()->root_folder_id;
        if (! is_string($folderId) || $folderId === '') {
            Notification::make()->danger()
                ->title(__('assestme.google_drive.errors.configuration_incomplete'))
                ->send();

            return;
        }

        try {
            $google->inspectFolder($folderId);
            Notification::make()->success()
                ->title(__('assestme.google_drive.notifications.connection_verified'))
                ->send();
        } catch (Throwable $exception) {
            Notification::make()->danger()
                ->title(__('assestme.google_drive.notifications.connection_failed'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function prepareRoot(GoogleWorkspaceClient $google): void
    {
        $settings = $this->settings();
        if (! $this->connected() || $this->configured()) {
            Notification::make()->danger()
                ->title(__('assestme.google_drive.errors.root_preparation_unavailable'))
                ->send();

            return;
        }

        try {
            $root = $google->createApplicationRoot();
            $settings->root_folder_id = $root->id;
            $settings->root_folder_name = $root->name;
            $settings->sync_enabled = true;
            $settings->save();

            Notification::make()->success()
                ->title(__('assestme.google_drive.notifications.root_created'))
                ->send();
        } catch (Throwable) {
            $settings->sync_enabled = false;
            $settings->save();

            Notification::make()->danger()
                ->title(__('assestme.google_drive.notifications.root_creation_failed'))
                ->body(__('assestme.google_drive.notifications.root_creation_failed_body'))
                ->send();
        }
    }

    public function disconnect(GoogleDriveTokenService $tokens): void
    {
        $revoked = false;
        try {
            $revoked = $tokens->revoke();
        } catch (Throwable) {
            $revoked = false;
        }

        $settings = $this->settings();
        $settings->sync_enabled = false;
        $settings->google_account_email = null;
        $settings->encrypted_refresh_token = null;
        $settings->root_folder_id = null;
        $settings->root_folder_name = null;
        $settings->save();

        $notification = Notification::make()
            ->title(__('assestme.google_drive.notifications.disconnected'));
        if ($revoked) {
            $notification->success();
        } else {
            $notification->warning()
                ->body(__('assestme.google_drive.notifications.remote_revoke_failed'));
        }
        $notification->send();
    }

    private function configuration(): GoogleDriveConfiguration
    {
        return app(GoogleDriveConfiguration::class);
    }

    private function secretHelp(bool $configured): string
    {
        return $configured
            ? __('assestme.google_drive.help.secret_configured')
            : __('assestme.google_drive.help.secret_missing');
    }

    private function replacementSecret(mixed $candidate, ?string $current): ?string
    {
        return $this->normalizedString($candidate) ?? $current;
    }

    private function normalizedString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function changed(?string $before, ?string $after): bool
    {
        if ($before === null || $after === null) {
            return $before !== $after;
        }

        return ! hash_equals($before, $after);
    }
}
