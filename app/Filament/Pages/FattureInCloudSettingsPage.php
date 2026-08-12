<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Data\FattureInCloud\FattureInCloudVatTypeData;
use App\Filament\Clusters\IntegrationsCluster;
use App\Filament\Pages\Concerns\HasParentSettingsNavigation;
use App\Filament\Support\ClipboardCopyAction;
use App\Services\FattureInCloud\FattureInCloudApi;
use App\Services\FattureInCloud\FattureInCloudConfiguration;
use App\Services\FattureInCloud\FattureInCloudTokenService;
use App\Settings\FattureInCloudSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Throwable;

final class FattureInCloudSettingsPage extends SettingsPage
{
    use HasParentSettingsNavigation;

    protected static ?string $cluster = IntegrationsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static ?int $navigationSort = 1;

    protected static string $settings = FattureInCloudSettings::class;

    protected string $view = 'filament.pages.fatture-in-cloud-settings-page';

    private bool $connectionResetAfterSave = false;

    public static function getNavigationLabel(): string
    {
        return __('assestme.fatture_in_cloud.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.fatture_in_cloud.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.fatture_in_cloud.configuration.heading'))
                ->description(__('assestme.fatture_in_cloud.configuration.description'))
                ->schema([
                    TextInput::make('client_id')
                        ->label(__('assestme.fatture_in_cloud.fields.client_id'))
                        ->required()
                        ->maxLength(512)
                        ->extraInputAttributes(['dusk' => 'fic-client-id']),
                    TextInput::make('client_secret')
                        ->label(__('assestme.fatture_in_cloud.fields.client_secret'))
                        ->helperText(fn (): string => $this->configuration()->clientSecret() === null
                            ? __('assestme.fatture_in_cloud.help.secret_missing')
                            : __('assestme.fatture_in_cloud.help.secret_configured'))
                        ->password()
                        ->autocomplete('new-password')
                        ->required(fn (): bool => $this->configuration()->clientSecret() === null)
                        ->maxLength(2048)
                        ->extraInputAttributes(['dusk' => 'fic-client-secret']),
                    TextInput::make('callback_uri')
                        ->label(__('assestme.fatture_in_cloud.fields.callback_uri'))
                        ->helperText(__('assestme.fatture_in_cloud.help.callback_uri'))
                        ->disabled()
                        ->dehydrated(false)
                        ->suffixAction(
                            ClipboardCopyAction::make()
                                ->copyMessage(__('assestme.fatture_in_cloud.notifications.callback_copied')),
                        )
                        ->columnSpanFull(),
                    Select::make('default_vat_type_id')
                        ->label(__('assestme.fatture_in_cloud.fields.default_vat'))
                        ->options(fn (): array => $this->vatOptions())
                        ->required(fn (): bool => $this->connected())
                        ->visible(fn (): bool => $this->connected())
                        ->native(false)
                        ->searchable()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public function connection(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.fatture_in_cloud.connection.heading'))
                ->description(__('assestme.fatture_in_cloud.connection.description'))
                ->schema([
                    Callout::make(__('assestme.fatture_in_cloud.states.configuration_missing_heading'))
                        ->description(__('assestme.fatture_in_cloud.states.configuration_missing'))
                        ->warning()
                        ->visible(fn (): bool => ! $this->configuration()->isComplete()),
                    Callout::make(__('assestme.fatture_in_cloud.states.disconnected_heading'))
                        ->description(__('assestme.fatture_in_cloud.states.disconnected'))
                        ->info()
                        ->visible(fn (): bool => $this->configuration()->isComplete() && ! $this->connected()),
                    Callout::make(__('assestme.fatture_in_cloud.states.vat_missing_heading'))
                        ->description(__('assestme.fatture_in_cloud.states.vat_missing'))
                        ->warning()
                        ->visible(fn (): bool => $this->connected() && ! $this->ready()),
                    TextEntry::make('company')
                        ->label(__('assestme.fatture_in_cloud.fields.company'))
                        ->state(fn (): ?string => $this->settings()->company_name)
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->visible(fn (): bool => $this->connected()),
                    TextEntry::make('status')
                        ->label(__('assestme.fatture_in_cloud.fields.status'))
                        ->state(fn (): string => $this->ready()
                            ? __('assestme.fatture_in_cloud.states.ready')
                            : __('assestme.fatture_in_cloud.states.incomplete'))
                        ->badge()
                        ->color(fn (): string => $this->ready() ? 'success' : 'warning')
                        ->visible(fn (): bool => $this->connected()),
                    Actions::make([
                        Action::make('connect_fic')
                            ->label(__('assestme.fatture_in_cloud.actions.connect'))
                            ->icon(Heroicon::OutlinedLink)
                            ->url(fn (): string => route('fatture-in-cloud.oauth.redirect'))
                            ->visible(fn (): bool => $this->configuration()->isComplete() && ! $this->connected()),
                        Action::make('reconnect_fic')
                            ->label(__('assestme.fatture_in_cloud.actions.reconnect'))
                            ->icon(Heroicon::OutlinedArrowPath)
                            ->color('gray')
                            ->url(fn (): string => route('fatture-in-cloud.oauth.redirect'))
                            ->visible(fn (): bool => $this->connected()),
                        Action::make('verify_fic')
                            ->label(__('assestme.fatture_in_cloud.actions.verify'))
                            ->icon(Heroicon::OutlinedCheckCircle)
                            ->color('gray')
                            ->action(function (): void {
                                $this->verifyConnection();
                            })
                            ->visible(fn (): bool => $this->connected()),
                        Action::make('disconnect_fic')
                            ->label(__('assestme.fatture_in_cloud.actions.disconnect'))
                            ->icon(Heroicon::OutlinedLinkSlash)
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalDescription(__('assestme.fatture_in_cloud.help.provider_revoke'))
                            ->action(function (): void {
                                $this->disconnect();
                            })
                            ->visible(fn (): bool => $this->connected()),
                    ])->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'client_id' => $this->configuration()->clientId(),
            'client_secret' => null,
            'callback_uri' => $this->configuration()->callbackUri(),
            'default_vat_type_id' => $this->settings()->default_vat_type_id,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settings = $this->settings();
        $clientId = $this->normalized($data['client_id'] ?? null);
        $clientSecret = $this->normalized($data['client_secret'] ?? null)
            ?? $settings->encrypted_client_secret;
        $this->connectionResetAfterSave = $this->connected()
            && ($this->changed($settings->client_id, $clientId)
                || $this->changed($settings->encrypted_client_secret, $clientSecret));

        $values = [
            'client_id' => $clientId,
            'encrypted_client_secret' => $clientSecret,
        ];

        if ($this->connectionResetAfterSave) {
            return $values + $this->emptyConnection();
        }

        if ($this->connected()) {
            $vatId = $this->normalizedIdentifier($data['default_vat_type_id'] ?? null);
            $vatType = collect($this->vatTypes())->first(
                static fn (FattureInCloudVatTypeData $candidate): bool => $candidate->id === $vatId,
            );
            if (! $vatType instanceof FattureInCloudVatTypeData || $vatType->disabled) {
                throw ValidationException::withMessages([
                    'data.default_vat_type_id' => __('assestme.fatture_in_cloud.errors.invalid_vat'),
                ]);
            }
            $values['default_vat_type_id'] = $vatType->id;
            $values['default_vat_type_label'] = $vatType->label();
        }

        return $values;
    }

    protected function afterSave(): void
    {
        $this->data['client_secret'] = null;
    }

    public function getSavedNotificationTitle(): string
    {
        return $this->connectionResetAfterSave
            ? __('assestme.fatture_in_cloud.notifications.configuration_saved_reconnect')
            : __('assestme.fatture_in_cloud.notifications.configuration_saved');
    }

    public function connected(): bool
    {
        $settings = $this->settings();

        return is_string($settings->encrypted_refresh_token)
            && $settings->encrypted_refresh_token !== ''
            && is_string($settings->company_id)
            && $settings->company_id !== ''
            && $settings->scope_version === FattureInCloudConfiguration::SCOPE_VERSION;
    }

    public function ready(): bool
    {
        return $this->connected()
            && is_string($this->settings()->default_vat_type_id)
            && $this->settings()->default_vat_type_id !== '';
    }

    public function verifyConnection(): void
    {
        try {
            $companies = app(FattureInCloudApi::class)->companies();
            $settings = $this->settings();
            if (count($companies) !== 1 || $companies[0]->id !== $settings->company_id) {
                throw new \RuntimeException(__('assestme.fatture_in_cloud.errors.company_changed'));
            }
            $vatIds = array_map(
                static fn (FattureInCloudVatTypeData $vatType): string => $vatType->id,
                $this->vatTypes(),
            );
            if (! in_array($settings->default_vat_type_id, $vatIds, true)) {
                throw new \RuntimeException(__('assestme.fatture_in_cloud.errors.invalid_vat'));
            }
            Notification::make()->success()
                ->title(__('assestme.fatture_in_cloud.notifications.connection_verified'))
                ->send();
        } catch (Throwable) {
            Notification::make()->danger()
                ->title(__('assestme.fatture_in_cloud.notifications.connection_failed'))
                ->body(__('assestme.fatture_in_cloud.errors.provider_unavailable'))
                ->send();
        }
    }

    public function disconnect(): void
    {
        app(FattureInCloudTokenService::class)->clearConnection();
        $this->data['default_vat_type_id'] = null;
        Notification::make()->success()
            ->title(__('assestme.fatture_in_cloud.notifications.disconnected'))
            ->body(__('assestme.fatture_in_cloud.help.provider_revoke'))
            ->send();
    }

    public function settings(): FattureInCloudSettings
    {
        return app(FattureInCloudSettings::class);
    }

    /** @return array<string, string> */
    private function vatOptions(): array
    {
        $options = [];
        foreach ($this->vatTypes() as $vatType) {
            $options[$vatType->id] = $vatType->label();
        }

        return $options;
    }

    /** @return list<FattureInCloudVatTypeData> */
    private function vatTypes(): array
    {
        $companyId = $this->settings()->company_id;
        if (! $this->connected() || ! is_string($companyId)) {
            return [];
        }

        try {
            return array_values(array_filter(
                app(FattureInCloudApi::class)->vatTypes($companyId),
                static fn (FattureInCloudVatTypeData $vatType): bool => ! $vatType->disabled,
            ));
        } catch (Throwable) {
            return [];
        }
    }

    private function configuration(): FattureInCloudConfiguration
    {
        return app(FattureInCloudConfiguration::class);
    }

    /** @return array<string, null> */
    private function emptyConnection(): array
    {
        return [
            'encrypted_access_token' => null,
            'access_token_expires_at' => null,
            'encrypted_refresh_token' => null,
            'company_id' => null,
            'company_name' => null,
            'default_vat_type_id' => null,
            'default_vat_type_label' => null,
            'scope_version' => null,
        ];
    }

    private function normalized(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function normalizedIdentifier(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        return $this->normalized($value);
    }

    private function changed(?string $before, ?string $after): bool
    {
        if ($before === null || $after === null) {
            return $before !== $after;
        }

        return ! hash_equals($before, $after);
    }
}
