<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\DeleteBackup;
use App\Actions\Backups\VerifyBackup;
use App\Actions\Operations\RecordOperationalCheck;
use App\Data\Backups\BackupArchiveData;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Filament\Clusters\SettingsCluster;
use App\Services\Backups\BackupArchiveCatalog;
use App\Services\Operations\OperationalCheckStore;
use App\Settings\GeneralSettings;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Throwable;

final class BackupSettingsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.backup-settings-page';

    /** @var array<string, bool|int|string>|null */
    private ?array $cachedBackupOverview = null;

    public static function getNavigationLabel(): string
    {
        return __('assestme.backups.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.backups.title');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('assestme.backups.overview.heading'))
                    ->description(__('assestme.backups.overview.description'))
                    ->icon(Heroicon::OutlinedCircleStack)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'xl' => 4,
                    ])
                    ->schema([
                        TextEntry::make('latest_backup')
                            ->label(__('assestme.backups.overview.last_backup'))
                            ->state(fn (): string => (string) $this->backupOverview()['latest_backup'])
                            ->icon(Heroicon::OutlinedClock)
                            ->weight(FontWeight::SemiBold),
                        TextEntry::make('ordinary_count')
                            ->label(__('assestme.backups.overview.ordinary_count'))
                            ->state(fn (): int => (int) $this->backupOverview()['ordinary_count'])
                            ->icon(Heroicon::OutlinedArchiveBox),
                        TextEntry::make('safety_count')
                            ->label(__('assestme.backups.overview.safety_count'))
                            ->state(fn (): int => (int) $this->backupOverview()['safety_count'])
                            ->icon(Heroicon::OutlinedShieldCheck),
                        TextEntry::make('total_size')
                            ->label(__('assestme.backups.overview.total_size'))
                            ->state(fn (): string => (string) $this->backupOverview()['total_size'])
                            ->icon(Heroicon::OutlinedCircleStack),
                        TextEntry::make('root')
                            ->label(__('assestme.backups.overview.directory'))
                            ->state(fn (): string => (string) $this->backupOverview()['root'])
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->columnSpan([
                                'default' => 1,
                                'sm' => 2,
                            ]),
                        TextEntry::make('directory_exists')
                            ->label(__('assestme.backups.overview.directory_exists'))
                            ->state(fn (): string => $this->directoryState('directory_exists'))
                            ->badge()
                            ->color(fn (): string => $this->backupOverview()['directory_exists'] ? 'success' : 'danger')
                            ->icon(fn (): Heroicon => $this->backupOverview()['directory_exists']
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle),
                        TextEntry::make('directory_writable')
                            ->label(__('assestme.backups.overview.directory_writable'))
                            ->state(fn (): string => $this->directoryState('directory_writable'))
                            ->badge()
                            ->color(fn (): string => $this->backupOverview()['directory_writable'] ? 'success' : 'danger')
                            ->icon(fn (): Heroicon => $this->backupOverview()['directory_writable']
                                ? Heroicon::OutlinedCheckCircle
                                : Heroicon::OutlinedXCircle),
                        TextEntry::make('retention')
                            ->label(__('assestme.backups.overview.retention'))
                            ->state(fn (): string => __('assestme.backups.overview.retention_values', $this->backupOverview()))
                            ->icon(Heroicon::OutlinedCalendarDays),
                        TextEntry::make('operational_status')
                            ->label(__('assestme.backups.overview.operational_status'))
                            ->state(fn (): string => (string) $this->backupOverview()['operational_status'])
                            ->badge()
                            ->color(fn (): string => (string) $this->backupOverview()['operational_color']),
                        Callout::make(__('assestme.backups.overview.schedule_heading'))
                            ->description(__('assestme.backups.overview.scheduler_required'))
                            ->info()
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->footer([
                                Text::make(__('assestme.backups.overview.schedule_value'))
                                    ->weight(FontWeight::SemiBold),
                            ])
                            ->columnSpanFull(),
                    ]),
                EmbeddedTable::make(),
                Section::make(__('assestme.backups.restore.heading'))
                    ->description(__('assestme.backups.restore.description'))
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->columns(1)
                    ->schema([
                        Callout::make(__('assestme.backups.restore.cli_only_heading'))
                            ->description(__('assestme.backups.restore.cli_only_description'))
                            ->warning(),
                        Text::make(__('assestme.backups.restore.choose_archive'))
                            ->icon(Heroicon::OutlinedCursorArrowRays),
                        Text::make(__('assestme.backups.restore.reverifies'))
                            ->icon(Heroicon::OutlinedShieldCheck),
                        Text::make(__('assestme.backups.restore.safety_backup'))
                            ->icon(Heroicon::OutlinedShieldCheck),
                        Text::make(__('assestme.backups.restore.diagnostics'))
                            ->icon(Heroicon::OutlinedShieldCheck),
                        Text::make(__('assestme.backups.restore.rollback'))
                            ->icon(Heroicon::OutlinedShieldCheck),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('assestme.backups.table.heading'))
            ->records(fn (): array => collect(app(BackupArchiveCatalog::class)->all())
                ->mapWithKeys(static fn (BackupArchiveData $archive): array => [
                    $archive->name => $archive->toTableRecord(),
                ])
                ->all())
            ->columns([
                TextColumn::make('name')
                    ->label(__('assestme.backups.table.name'))
                    ->copyable(),
                TextColumn::make('kind')
                    ->label(__('assestme.backups.table.kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __("assestme.backups.types.{$state}"))
                    ->color(fn (string $state): string => $state === 'safety' ? 'warning' : 'primary'),
                TextColumn::make('modified_at')
                    ->label(__('assestme.backups.table.modified_at'))
                    ->dateTime('d/m/Y H:i', app(GeneralSettings::class)->timezone),
                TextColumn::make('size')
                    ->label(__('assestme.backups.table.size'))
                    ->formatStateUsing(fn (int $state): string => $this->formatBytes($state)),
            ])
            ->headerActions([
                Action::make('createBackup')
                    ->label(__('assestme.backups.actions.create'))
                    ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                    ->action(fn (): null => $this->createBackup()),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label(__('assestme.backups.actions.verify'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->action(fn (array $record): null => $this->verifyArchive($record)),
                Action::make('download')
                    ->label(__('assestme.backups.actions.download'))
                    ->icon(Heroicon::OutlinedCloudArrowDown)
                    ->url(fn (array $record): string => route('backups.download', ['archive' => $record['name']])),
                Action::make('restoreInstructions')
                    ->label(__('assestme.backups.actions.restore_instructions'))
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->modalHeading(__('assestme.backups.restore.instructions_title'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('assestme.backups.actions.close'))
                    ->modalWidth(Width::FourExtraLarge)
                    ->schema(fn (array $record): array => $this->restoreInstructionComponents($record)),
                Action::make('delete')
                    ->label(__('assestme.backups.actions.delete'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('assestme.backups.delete.heading'))
                    ->modalDescription(fn (array $record): string => __('assestme.backups.delete.description', [
                        'name' => $record['name'],
                    ]))
                    ->modalSubmitActionLabel(__('assestme.backups.delete.confirm'))
                    ->action(fn (array $record): null => $this->deleteArchive($record)),
            ])
            ->emptyStateHeading(__('assestme.backups.table.empty'))
            ->emptyStateDescription(__('assestme.backups.table.empty_description'))
            ->emptyStateIcon(Heroicon::OutlinedArchiveBox)
            ->paginated(false);
    }

    /**
     * @return array{
     *     latest_backup: string,
     *     ordinary_count: int,
     *     safety_count: int,
     *     total_size: string,
     *     root: string,
     *     directory_exists: bool,
     *     directory_writable: bool,
     *     daily: int,
     *     weekly: int,
     *     monthly: int,
     *     operational_status: string,
     *     operational_color: string
     * }
     */
    public function backupOverview(): array
    {
        if ($this->cachedBackupOverview !== null) {
            /** @var array{
             *     latest_backup: string,
             *     ordinary_count: int,
             *     safety_count: int,
             *     total_size: string,
             *     root: string,
             *     directory_exists: bool,
             *     directory_writable: bool,
             *     daily: int,
             *     weekly: int,
             *     monthly: int,
             *     operational_status: string,
             *     operational_color: string
             * } $overview
             */
            $overview = $this->cachedBackupOverview;

            return $overview;
        }

        $catalog = app(BackupArchiveCatalog::class);
        $archives = $catalog->all();
        $ordinary = array_values(array_filter(
            $archives,
            static fn (BackupArchiveData $archive): bool => $archive->kind === 'backup',
        ));
        $root = $catalog->configuredRoot();
        $latest = $ordinary[0] ?? null;
        $check = app(OperationalCheckStore::class)->find(OperationalCheckType::Backup);

        $this->cachedBackupOverview = [
            'latest_backup' => $latest === null ? '—' : $this->formatDate($latest->modifiedAt),
            'ordinary_count' => count($ordinary),
            'safety_count' => count($archives) - count($ordinary),
            'total_size' => $this->formatBytes(array_sum(array_map(
                static fn (BackupArchiveData $archive): int => $archive->size,
                $archives,
            ))),
            'root' => $root,
            'directory_exists' => is_dir($root),
            'directory_writable' => is_dir($root) && is_writable($root),
            'daily' => (int) config('assestme.backup.retention.daily'),
            'weekly' => (int) config('assestme.backup.retention.weekly'),
            'monthly' => (int) config('assestme.backup.retention.monthly'),
            'operational_status' => match ($check?->status) {
                OperationalCheckStatus::Succeeded => __('assestme.backups.overview.status_succeeded'),
                OperationalCheckStatus::Failed => __('assestme.backups.overview.status_failed'),
                default => __('assestme.backups.overview.status_unknown'),
            },
            'operational_color' => match ($check?->status) {
                OperationalCheckStatus::Succeeded => 'success',
                OperationalCheckStatus::Failed => 'danger',
                default => 'gray',
            },
        ];

        return $this->backupOverview();
    }

    private function createBackup(): null
    {
        try {
            $path = app(CreateBackup::class)(null);
            $this->recordBackupCheck(OperationalCheckStatus::Succeeded);
            $this->cachedBackupOverview = null;
            $this->resetTable();

            Notification::make()
                ->success()
                ->title(__('assestme.backups.notifications.created'))
                ->body(__('assestme.backups.notifications.created_body', ['name' => basename($path)]))
                ->send();
        } catch (Throwable $exception) {
            $this->logFailure('Backup creation from the Filament page failed.', $exception);
            $this->recordBackupCheck(OperationalCheckStatus::Failed, $exception->getMessage());

            Notification::make()
                ->danger()
                ->title(__('assestme.backups.errors.creation_failed'))
                ->body(__('assestme.backups.errors.retry'))
                ->send();
        }

        return null;
    }

    /** @param array<string, mixed> $record */
    private function verifyArchive(array $record): null
    {
        $name = $this->recordName($record);

        try {
            $path = app(BackupArchiveCatalog::class)->resolveManagedArchive($name);
            $manifest = app(VerifyBackup::class)->handle($path);

            Notification::make()
                ->success()
                ->title(__('assestme.backups.notifications.verified'))
                ->body(__('assestme.backups.notifications.verified_body', [
                    'count' => count($manifest->files()),
                    'date' => CarbonImmutable::parse($manifest->createdAt)
                        ->setTimezone(app(GeneralSettings::class)->timezone)
                        ->format('d/m/Y H:i'),
                ]))
                ->send();
        } catch (Throwable $exception) {
            $this->logFailure('Backup verification from the Filament page failed.', $exception, $name);

            Notification::make()
                ->danger()
                ->title(__('assestme.backups.errors.verification_failed'))
                ->body(__('assestme.backups.errors.corrupt'))
                ->send();
        }

        return null;
    }

    /** @param array<string, mixed> $record */
    private function deleteArchive(array $record): null
    {
        $name = $this->recordName($record);

        try {
            app(DeleteBackup::class)($name);
            $this->cachedBackupOverview = null;
            $this->resetTable();

            Notification::make()
                ->success()
                ->title(__('assestme.backups.notifications.deleted'))
                ->body(__('assestme.backups.notifications.deleted_body', ['name' => $name]))
                ->send();
        } catch (Throwable $exception) {
            $this->logFailure('Backup deletion from the Filament page failed.', $exception, $name);

            Notification::make()
                ->danger()
                ->title(__('assestme.backups.errors.deletion_failed'))
                ->body(__('assestme.backups.errors.retry'))
                ->send();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<Callout|Section>
     */
    private function restoreInstructionComponents(array $record): array
    {
        $name = $this->recordName($record);

        try {
            $path = app(BackupArchiveCatalog::class)->resolveManagedArchive($name);
            $quotedPath = escapeshellarg($path);
            $commands = [
                "php artisan assestme:backup:verify {$quotedPath}",
                'php artisan down',
                "php artisan assestme:restore-backup {$quotedPath}",
                'php artisan up',
            ];

            return [
                Callout::make(__('assestme.backups.restore.selected_archive', ['name' => $name]))
                    ->description(__('assestme.backups.restore.instructions_lead'))
                    ->info(),
                Section::make(__('assestme.backups.restore.commands_heading'))
                    ->description(__('assestme.backups.restore.commands_description'))
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->columns(1)
                    ->schema(array_map(
                        static fn (string $command): Text => Text::make($command)
                            ->fontFamily(FontFamily::Mono)
                            ->copyable()
                            ->copyMessage(__('assestme.backups.restore.command_copied')),
                        $commands,
                    )),
                Section::make(__('assestme.backups.restore.safeguards_heading'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columns(1)
                    ->schema([
                        Text::make(__('assestme.backups.restore.reverifies'))
                            ->icon(Heroicon::OutlinedCheckCircle),
                        Text::make(__('assestme.backups.restore.safety_backup'))
                            ->icon(Heroicon::OutlinedCheckCircle),
                        Text::make(__('assestme.backups.restore.diagnostics'))
                            ->icon(Heroicon::OutlinedCheckCircle),
                        Text::make(__('assestme.backups.restore.rollback'))
                            ->icon(Heroicon::OutlinedCheckCircle),
                    ]),
            ];
        } catch (Throwable $exception) {
            $this->logFailure('Backup restore instructions could not be prepared.', $exception, $name);

            return [
                Callout::make(__('assestme.backups.errors.not_found'))
                    ->description(__('assestme.backups.errors.restore_instructions_unavailable'))
                    ->danger(),
            ];
        }
    }

    private function directoryState(string $key): string
    {
        return (bool) $this->backupOverview()[$key]
            ? __('assestme.backups.overview.available')
            : __('assestme.backups.overview.unavailable');
    }

    private function recordBackupCheck(OperationalCheckStatus $status, ?string $error = null): void
    {
        try {
            app(RecordOperationalCheck::class)(
                OperationalCheckType::Backup,
                $status,
                $error,
            );
        } catch (Throwable $exception) {
            $this->logFailure('Backup operational status could not be persisted.', $exception);
        }
    }

    /** @param array<string, mixed> $record */
    private function recordName(array $record): string
    {
        return is_string($record['name'] ?? null) ? $record['name'] : '';
    }

    private function formatDate(CarbonImmutable $date): string
    {
        return $date->setTimezone(app(GeneralSettings::class)->timezone)->format('d/m/Y H:i');
    }

    private function formatBytes(int $bytes): string
    {
        return Number::fileSize($bytes, precision: 1, maxPrecision: 2);
    }

    private function logFailure(string $message, Throwable $exception, ?string $archive = null): void
    {
        Log::error($message, array_filter([
            'archive' => $archive,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ], static fn (mixed $value): bool => $value !== null));
    }
}
