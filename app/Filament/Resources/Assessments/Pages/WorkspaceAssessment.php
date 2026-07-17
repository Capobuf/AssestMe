<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Actions\Assessments\CompleteAssessment;
use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\CreateBlankFinding;
use App\Actions\Assessments\DuplicateFinding;
use App\Actions\Assessments\ReopenAssessment;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Evidence\StoreEvidenceFile;
use App\Actions\Evidence\StoreEvidenceUrl;
use App\Actions\Reports\DeleteGeneratedReport;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Data\Assessments\WorkspaceSaveData;
use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\ScopeType;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Schemas\AssessmentWorkspaceForm;
use App\Models\Assessment;
use App\Models\FindingTemplate;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class WorkspaceAssessment extends EditRecord
{
    protected Width|string|null $maxContentWidth = Width::Full;

    public const STATUS_SAVED = 'saved';

    public const STATUS_UNSAVED = 'unsaved';

    public const STATUS_SAVING = 'saving';

    public const STATUS_ERROR = 'error';

    public const STATUS_CONFLICT = 'conflict';

    protected static string $resource = AssessmentResource::class;

    public int $expectedVersion = 0;

    public string $tabId = '';

    public string $saveStatus = self::STATUS_SAVED;

    public ?string $saveError = null;

    public bool $saveInProgress = false;

    public bool $saveQueued = false;

    /** @var array<string, int> */
    public array $persistedFindingIds = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $assessment = $this->assessment();
        $this->expectedVersion = (int) $assessment->lock_version;
        $this->tabId = (string) Str::uuid();
    }

    public function form(Schema $schema): Schema
    {
        return AssessmentWorkspaceForm::configure($schema);
    }

    public function save(bool $shouldRedirect = false, bool $shouldSendSavedNotification = true): void
    {
        $this->persistWorkspace(isAutosave: false, shouldNotify: $shouldSendSavedNotification);
    }

    public function autosave(): void
    {
        $this->saveStatus = self::STATUS_UNSAVED;

        if ($this->saveInProgress) {
            $this->saveQueued = true;

            return;
        }

        $this->persistWorkspace(isAutosave: true, shouldNotify: false);
    }

    public function getSaveStatusLabel(): string
    {
        return __('assestme.workspace.status.'.$this->saveStatus);
    }

    public function getTitle(): string
    {
        return __('assestme.workspace.title');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_blank')
                ->label(__('assestme.workspace.add_finding'))
                ->icon('heroicon-o-plus')
                ->extraAttributes(['data-dusk' => 'add-finding'])
                ->visible(fn (): bool => ! $this->isWorkspaceReadOnly())
                ->action(function (): void {
                    app(CreateBlankFinding::class)->handle($this->assessment());
                    $this->fillForm();
                }),
            Action::make('add_template')
                ->label(__('assestme.workspace.add_template'))
                ->icon('heroicon-o-book-open')
                ->extraAttributes(['data-dusk' => 'add-template'])
                ->visible(fn (): bool => ! $this->isWorkspaceReadOnly())
                ->schema([
                    Select::make('template_id')
                        ->label(__('assestme.workspace.template'))
                        ->options(fn (): array => FindingTemplate::query()
                            ->where('is_enabled', true)
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $template = FindingTemplate::query()->findOrFail($data['template_id']);
                    app(CopyTemplateToAssessment::class)->handle($this->assessment(), $template);
                    $this->fillForm();
                }),
            Action::make('complete')
                ->label(__('assestme.workspace.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->assessment()->status === AssessmentStatus::Draft)
                ->action(function (): void {
                    $assessment = app(CompleteAssessment::class)($this->assessment());
                    // A full reload clears every modal Alpine scope before switching the entire workspace to read-only.
                    $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $assessment]), navigate: false);
                }),
            Action::make('reopen')
                ->label(__('assestme.workspace.reopen'))
                ->icon('heroicon-o-lock-open')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->assessment()->status !== AssessmentStatus::Draft)
                ->action(function (): void {
                    $assessment = app(ReopenAssessment::class)($this->assessment());
                    $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $assessment]), navigate: false);
                }),
            Action::make('download_pdf')
                ->label(__('assestme.workspace.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->extraAttributes(['data-dusk' => 'generate-pdf'])
                ->action(function (): void {
                    $this->generatePdf();
                }),
            Action::make('download_xlsx')
                ->label(__('assestme.workspace.download_xlsx'))
                ->icon('heroicon-o-table-cells')
                ->extraAttributes(['data-dusk' => 'generate-xlsx'])
                ->modalHeading(__('assestme.workspace.generate_xlsx_heading'))
                ->modalSubmitActionLabel(__('assestme.workspace.generate_xlsx_confirm'))
                ->schema([
                    Toggle::make('include_excluded_findings')
                        ->label(__('assestme.workspace.include_excluded_findings_in_xlsx'))
                        ->default(fn (): bool => app(GeneralSettings::class)->report_excluded_findings_in_xlsx),
                ])
                ->action(function (array $data): void {
                    $this->generateWorkbook((bool) ($data['include_excluded_findings'] ?? false));
                }),
        ];
    }

    private function generatePdf(): void
    {
        try {
            $report = app(GenerateAssessmentPdf::class)->handle($this->assessment());

            Notification::make()
                ->success()
                ->title(__('assestme.reports.generated'))
                ->body($report->file_name)
                ->send();

            // Reloading applies a possible freeze atomically and exposes the immutable file in history.
            $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $this->assessment()]), navigate: false);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('assestme.reports.errors.generation'))
                ->body(collect($exception->errors())->flatten()->join(' '))
                ->persistent()
                ->send();

        } catch (Throwable $exception) {
            Log::error('Assessment PDF generation failed.', [
                'assessment_id' => $this->assessment()->getKey(),
                'exception' => $exception,
            ]);

            Notification::make()
                ->danger()
                ->title(__('assestme.reports.errors.generation'))
                ->body(__('assestme.reports.errors.retry'))
                ->persistent()
                ->send();

        }
    }

    private function generateWorkbook(bool $includeExcludedFindings): void
    {
        try {
            $report = app(GenerateAssessmentWorkbook::class)->handle(
                $this->assessment(),
                $includeExcludedFindings,
            );

            Notification::make()
                ->success()
                ->title(__('assestme.reports.generated_xlsx'))
                ->body($report->file_name)
                ->send();

            $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $this->assessment()]), navigate: false);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(__('assestme.reports.errors.generation_xlsx'))
                ->body(collect($exception->errors())->flatten()->join(' '))
                ->persistent()
                ->send();
        } catch (Throwable $exception) {
            Log::error('Assessment XLSX generation failed.', [
                'assessment_id' => $this->assessment()->getKey(),
                'exception' => $exception,
            ]);

            Notification::make()
                ->danger()
                ->title(__('assestme.reports.errors.generation_xlsx'))
                ->body(__('assestme.reports.errors.retry_xlsx'))
                ->persistent()
                ->send();
        }
    }

    public function deleteGeneratedReportAction(): Action
    {
        return Action::make('deleteGeneratedReport')
            ->label(__('assestme.reports.delete.action'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('assestme.reports.delete.heading'))
            ->modalDescription(__('assestme.reports.delete.description'))
            ->modalSubmitActionLabel(__('assestme.reports.delete.confirm'))
            ->extraAttributes(['data-dusk' => 'delete-generated-report'])
            ->action(function (array $arguments): void {
                $reportId = filter_var($arguments['report'] ?? null, FILTER_VALIDATE_INT);

                if (! is_int($reportId) || $reportId < 1) {
                    $this->reportGeneratedFileDeletionFailure(null, new \InvalidArgumentException('The generated report identifier is invalid.'));

                    return;
                }

                try {
                    $report = $this->assessment()->generatedReports()->findOrFail($reportId);
                    $operation = app(DeleteGeneratedReport::class)->handle($report);

                    if ($operation->status === DeletionOperationStatus::CleanupFailed) {
                        Log::warning('Generated report deleted with pending trash cleanup.', [
                            'assessment_id' => $this->assessment()->getKey(),
                            'generated_report_id' => $reportId,
                            'deletion_operation_uuid' => $operation->uuid,
                        ]);

                        Notification::make()
                            ->warning()
                            ->title(__('assestme.reports.delete.cleanup_pending'))
                            ->body(__('assestme.reports.delete.cleanup_pending_body'))
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('assestme.reports.delete.deleted'))
                        ->send();
                } catch (Throwable $exception) {
                    $this->reportGeneratedFileDeletionFailure($reportId, $exception);
                }
            });
    }

    private function reportGeneratedFileDeletionFailure(?int $reportId, Throwable $exception): void
    {
        Log::error('Generated report permanent deletion failed.', [
            'assessment_id' => $this->assessment()->getKey(),
            'generated_report_id' => $reportId,
            'exception' => $exception,
        ]);

        Notification::make()
            ->danger()
            ->title(__('assestme.reports.delete.failed'))
            ->body(__('assestme.reports.delete.failed_body'))
            ->persistent()
            ->send();
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(__('assestme.workspace.explicit_save'))
            ->extraAttributes(['data-dusk' => 'save-assessment'])
            ->visible(fn (): bool => ! $this->isWorkspaceReadOnly());
    }

    private function persistWorkspace(bool $isAutosave, bool $shouldNotify): void
    {
        if ($this->saveStatus === self::STATUS_CONFLICT) {
            return;
        }

        $this->saveInProgress = true;
        $this->saveStatus = self::STATUS_SAVING;
        $this->saveError = null;

        try {
            $rawState = $this->form->getRawState();
            $state = is_array($rawState) ? $rawState : $rawState->toArray();
            $rawFindings = is_array($state['findings'] ?? null) ? $state['findings'] : [];
            $findings = [];

            foreach ($rawFindings as $itemKey => $finding) {
                if (! is_array($finding)) {
                    continue;
                }

                $key = (string) $itemKey;
                $recordKey = str($key)->after('record-')->toString();
                $isExistingRecord = str_starts_with($key, 'record-') && ctype_digit($recordKey);
                $temporaryUuid = Str::isUuid($key)
                    ? $key
                    : (is_string($finding['_temporary_uuid'] ?? null) ? $finding['_temporary_uuid'] : (string) Str::uuid());

                $finding['id'] = $isExistingRecord
                    ? (int) $recordKey
                    : ($this->persistedFindingIds[$key] ?? null);
                $finding['_temporary_uuid'] = $temporaryUuid;
                $findings[] = $finding;
            }

            /** @var array{
             *     assessment: array<string, mixed>,
             *     findings: list<array<string, mixed>>
             * } $payload
             */
            $payload = [
                'assessment' => [
                    'title' => (string) ($state['title'] ?? ''),
                    'assessment_date' => (string) ($state['assessment_date'] ?? ''),
                    'report_title_override' => $state['report_title_override'] ?? null,
                    'scope_type' => $state['scope_type'] ?? ScopeType::Organization->value,
                    'scope_description' => $state['scope_description'] ?? null,
                    'introduction' => $state['introduction'] ?? null,
                    'executive_summary' => $state['executive_summary'] ?? null,
                    'methodology_notes' => $state['methodology_notes'] ?? null,
                    'site_ids' => is_array($state['site_ids'] ?? null) ? $state['site_ids'] : [],
                ],
                'findings' => $findings,
            ];

            $request = new WorkspaceSaveData(
                requestId: (string) Str::uuid(),
                expectedVersion: $this->expectedVersion,
                tabId: $this->tabId,
                payload: $payload,
                payloadSha256: WorkspaceSaveData::hashPayload($payload),
            );

            $result = app(SaveAssessmentWorkspace::class)($this->assessment(), $request);
            $this->expectedVersion = $result->appliedVersion;
            $this->applyPersistedIds($result->idMap);
            $this->assessment()->setAttribute('lock_version', $result->appliedVersion);
            $this->saveStatus = self::STATUS_SAVED;

            if ($shouldNotify && ! $isAutosave) {
                Notification::make()
                    ->title(__('assestme.workspace.saved_notification'))
                    ->success()
                    ->send();
            }
        } catch (AssessmentVersionConflict) {
            $this->saveStatus = self::STATUS_CONFLICT;
            $this->saveError = __('assestme.workspace.errors.conflict');
        } catch (ValidationException $exception) {
            $this->saveStatus = self::STATUS_ERROR;
            $this->saveError = __('assestme.workspace.errors.validation');

            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($this->formErrorKey($key), $message);
                }
            }
        } catch (IdempotencyKeyMismatch|LockTimeoutException $exception) {
            $this->reportSaveFailure($exception);
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
        } finally {
            $this->saveInProgress = false;
        }

        if ($this->saveQueued && $this->saveStatus !== self::STATUS_CONFLICT) {
            $this->saveQueued = false;
            $this->persistWorkspace(isAutosave: true, shouldNotify: false);
        }
    }

    /** @param array<string, int> $idMap */
    private function applyPersistedIds(array $idMap): void
    {
        if (! isset($this->data['findings']) || ! is_array($this->data['findings'])) {
            return;
        }

        $this->persistedFindingIds = array_replace($this->persistedFindingIds, $idMap);

        foreach ($this->data['findings'] as $itemKey => &$finding) {
            if (! is_array($finding)) {
                continue;
            }

            $key = (string) $itemKey;
            $temporaryUuid = Str::isUuid($key) ? $key : ($finding['_temporary_uuid'] ?? null);

            if (is_string($temporaryUuid) && isset($idMap[$temporaryUuid])) {
                $finding['id'] = $idMap[$temporaryUuid];
            }
        }

        unset($finding);
    }

    private function reportSaveFailure(Throwable $exception): void
    {
        $this->saveStatus = self::STATUS_ERROR;
        $this->saveError = __('assestme.workspace.errors.persistence');

        Log::error('Assessment workspace save failed.', [
            'assessment_id' => $this->assessment()->getKey(),
            'expected_version' => $this->expectedVersion,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function formErrorKey(string $key): string
    {
        return 'data.'.str($key)->replaceStart('payload.', '')->toString();
    }

    private function assessment(): Assessment
    {
        $record = $this->getRecord();

        if (! $record instanceof Assessment) {
            throw new \LogicException('The workspace record must be an assessment.');
        }

        return $record;
    }

    public function isWorkspaceReadOnly(): bool
    {
        return $this->assessment()->status !== AssessmentStatus::Draft;
    }

    public function duplicateFinding(int $findingId): void
    {
        $finding = $this->assessment()->findings()->findOrFail($findingId);
        app(DuplicateFinding::class)->handle($finding);
        $this->fillForm();
    }

    /** @param array<string, mixed> $data */
    public function saveFindingDetails(int $findingId, array $data): void
    {
        $finding = $this->assessment()->findings()->findOrFail($findingId);
        $uploads = is_array($data['evidence_uploads'] ?? null) ? $data['evidence_uploads'] : [];
        $originalNames = is_array($data['evidence_original_names'] ?? null) ? $data['evidence_original_names'] : [];
        $evidenceUrl = is_string($data['evidence_url'] ?? null) ? trim($data['evidence_url']) : '';
        $evidenceTitle = is_string($data['evidence_title'] ?? null) ? trim($data['evidence_title']) : '';

        try {
            app(SaveFindingDetails::class)->handle(
                $finding,
                collect($data)->except(['evidence_uploads', 'evidence_original_names', 'evidence_url', 'evidence_title'])->all(),
            );

            foreach ($uploads as $key => $upload) {
                if ($upload instanceof UploadedFile) {
                    $uploadedFile = $upload;
                } elseif (is_string($upload) && Storage::disk('local')->exists($upload)) {
                    $uploadedFile = new UploadedFile(
                        Storage::disk('local')->path($upload),
                        is_string($originalNames[$key] ?? null) ? $originalNames[$key] : basename($upload),
                        Storage::disk('local')->mimeType($upload),
                        null,
                        true,
                    );
                } else {
                    continue;
                }

                app(StoreEvidenceFile::class)->handle($finding, $uploadedFile, [
                    'title' => $uploadedFile->getClientOriginalName(),
                    'include_in_report' => true,
                ]);
            }
            if ($evidenceUrl !== '') {
                app(StoreEvidenceUrl::class)->handle($finding, [
                    'title' => $evidenceTitle,
                    'url' => $evidenceUrl,
                    'include_in_report' => true,
                ]);
            }
        } finally {
            // Filament stores modal uploads before the action runs; every pending file is removed after adoption or failure.
            Storage::disk('local')->delete(array_values(array_filter($uploads, 'is_string')));
        }

        $this->fillForm();
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $record;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'site_ids' => $this->assessment()->sites()->pluck('sites.id')->all(),
        ];
    }
}
