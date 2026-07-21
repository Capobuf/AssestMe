<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Actions\Assessments\AssessFindingCompleteness;
use App\Actions\Assessments\CompleteAssessment;
use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\CreateBlankFinding;
use App\Actions\Assessments\DeleteFinding;
use App\Actions\Assessments\DuplicateFinding;
use App\Actions\Assessments\ReopenAssessment;
use App\Actions\Assessments\ReorderFindings;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Evidence\StoreEvidence;
use App\Actions\Reports\DeleteGeneratedReport;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Data\Assessments\FindingSaveData;
use App\Data\Assessments\FindingSaveResult;
use App\Data\Assessments\WorkspaceSaveData;
use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\EvidenceType;
use App\Enums\ScopeType;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Schemas\AssessmentWorkspaceForm;
use App\Filament\Resources\Assessments\Schemas\FindingEditorSchema;
use App\Filament\Resources\Assessments\Tables\AssessmentFindingsTable;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

final class WorkspaceAssessment extends EditRecord implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.resources.assessments.pages.workspace-assessment';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static string $resource = AssessmentResource::class;

    public const STATUS_SAVED = 'saved';

    public const STATUS_UNSAVED = 'unsaved';

    public const STATUS_SAVING = 'saving';

    public const STATUS_ERROR = 'error';

    public const STATUS_CONFLICT = 'conflict';

    public int $expectedVersion = 0;

    public string $tabId = '';

    public string $saveStatus = self::STATUS_SAVED;

    public ?string $saveError = null;

    public ?int $totalFindingsCount = null;

    public ?int $reportFindingsCount = null;

    /** @var array<string, mixed> */
    public array $findingData = [];

    #[Url(as: 'finding', history: true)]
    public ?int $selectedFindingId = null;

    #[Url(as: 'workspace-tab', history: true)]
    public string $activeWorkspaceTab = 'findings';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->expectedVersion = (int) $this->assessmentRecord()->lock_version;
        $this->tabId = (string) Str::uuid();

        if ($this->selectedFindingId !== null) {
            $this->loadSelectedFinding($this->selectedFindingId, closeWhenMissing: true);
        }
    }

    public function form(Schema $schema): Schema
    {
        return AssessmentWorkspaceForm::configure($schema);
    }

    public function findingEditor(Schema $schema): Schema
    {
        return FindingEditorSchema::configure($schema)
            ->model($this->selectedFinding())
            ->statePath('findingData');
    }

    public function findingProperties(Schema $schema): Schema
    {
        return FindingEditorSchema::properties($schema)
            ->model($this->selectedFinding())
            ->statePath('findingData');
    }

    public function table(Table $table): Table
    {
        return AssessmentFindingsTable::configure($table, $this);
    }

    /** @return Builder<Finding> */
    public function findingsQuery(): Builder
    {
        return Finding::query()
            ->where('assessment_id', $this->assessmentRecord()->getKey())
            ->with(['category', 'priorityLevel', 'sites', 'assets', 'solutions:id,finding_id'])
            ->withCount(['solutions', 'evidences'])
            ->orderBy('sort_order');
    }

    public function updatedFindingData(): void
    {
        if ($this->saveStatus !== self::STATUS_CONFLICT) {
            $this->saveStatus = self::STATUS_UNSAVED;
            $this->saveError = null;
        }
    }

    public function setWorkspaceTab(string $tab): void
    {
        if (! in_array($tab, ['findings', 'assessment-details', 'summary', 'generated-files'], true)) {
            return;
        }

        $this->activeWorkspaceTab = $tab;
    }

    public function selectFinding(int $findingId): void
    {
        if ($this->selectedFindingId !== $findingId && $this->saveStatus === self::STATUS_UNSAVED) {
            $this->notifyUnsavedSelectionBlocked();

            return;
        }

        $this->loadSelectedFinding($findingId);
        $this->dispatch('assestme-finding-selected');
    }

    public function closeInspector(): void
    {
        if ($this->saveStatus === self::STATUS_UNSAVED) {
            $this->notifyUnsavedSelectionBlocked();

            return;
        }

        $this->selectedFindingId = null;
        $this->findingData = [];
        $this->saveStatus = self::STATUS_SAVED;
        $this->resetErrorBag();
    }

    public function selectPreviousFinding(): void
    {
        $this->selectAdjacentFinding(-1);
    }

    public function selectNextFinding(): void
    {
        $this->selectAdjacentFinding(1);
    }

    public function saveFinding(bool $moveNext = false): void
    {
        $finding = $this->selectedFinding();
        if (! $finding instanceof Finding || $this->isWorkspaceReadOnly() || $this->saveStatus === self::STATUS_CONFLICT) {
            return;
        }

        $this->saveStatus = self::STATUS_SAVING;
        $this->saveError = null;
        $this->resetErrorBag();

        $rawState = $this->findingEditorSchema()->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();
        $uploads = is_array($state['evidence_uploads'] ?? null) ? $state['evidence_uploads'] : [];
        $originalNames = is_array($state['evidence_original_names'] ?? null) ? $state['evidence_original_names'] : [];
        $evidenceUrl = is_string($state['evidence_url'] ?? null) ? trim($state['evidence_url']) : '';
        $evidenceTitle = is_string($state['evidence_title'] ?? null) ? trim($state['evidence_title']) : '';
        $payload = Arr::except($state, [
            'evidence_uploads', 'evidence_original_names', 'evidence_url', 'evidence_title', 'existing_evidence',
        ]);

        try {
            $result = $this->persistFindingPayload($finding, $payload);
            $finding = $result->finding;

            foreach ($uploads as $key => $upload) {
                $uploadedFile = $this->uploadedFile($upload, $originalNames[$key] ?? null);
                if (! $uploadedFile instanceof UploadedFile) {
                    continue;
                }

                app(StoreEvidence::class)(
                    $finding,
                    EvidenceType::File,
                    ['title' => $uploadedFile->getClientOriginalName(), 'include_in_report' => true],
                    $uploadedFile,
                    $this->expectedVersion,
                );
                $this->refreshExpectedVersion();
            }

            if ($evidenceUrl !== '') {
                app(StoreEvidence::class)(
                    $finding,
                    EvidenceType::Url,
                    ['title' => $evidenceTitle, 'url' => $evidenceUrl, 'include_in_report' => true],
                    expectedVersion: $this->expectedVersion,
                );
                $this->refreshExpectedVersion();
            }

            $this->loadSelectedFinding((int) $finding->getKey());
            $this->refreshListCounts();
            $this->saveStatus = self::STATUS_SAVED;

            Notification::make()
                ->success()
                ->title(__('assestme.workspace.inspector.saved'))
                ->send();

            if ($moveNext) {
                $this->selectAdjacentFinding(1);
            }
        } catch (AssessmentVersionConflict) {
            $this->saveStatus = self::STATUS_CONFLICT;
            $this->saveError = __('assestme.workspace.errors.conflict');
        } catch (ValidationException $exception) {
            $this->saveStatus = self::STATUS_ERROR;
            $this->saveError = __('assestme.workspace.errors.validation');
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('findingData.'.$key, $message);
                }
            }
            $this->dispatch('assestme-finding-validation-failed');
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
        } finally {
            Storage::disk('local')->delete(array_values(array_filter($uploads, 'is_string')));
        }
    }

    public function saveFindingAndNext(): void
    {
        $this->saveFinding(moveNext: true);
    }

    public function updateInlineStatus(Finding $finding, string $status): string
    {
        $payload = FindingEditorSchema::data($finding);
        $payload['status'] = $status;

        return $this->persistInline($finding, $payload, 'status');
    }

    public function updateInlineReportInclusion(Finding $finding, bool $included): bool
    {
        $payload = FindingEditorSchema::data($finding);
        $payload['include_in_report'] = $included;

        return (bool) $this->persistInline($finding, $payload, 'include_in_report');
    }

    public function createBlankFinding(): void
    {
        $finding = app(CreateBlankFinding::class)($this->assessmentRecord());
        $this->refreshExpectedVersion();
        $this->refreshListCounts();
        $this->selectFinding((int) $finding->getKey());
    }

    public function createFromTemplate(int $templateId): void
    {
        $template = FindingTemplate::query()->findOrFail($templateId);
        $finding = app(CopyTemplateToAssessment::class)($this->assessmentRecord(), $template);
        $this->refreshExpectedVersion();
        $this->refreshListCounts();
        $this->selectFinding((int) $finding->getKey());
    }

    public function duplicateFinding(int $findingId): void
    {
        $source = $this->assessmentRecord()->findings()->findOrFail($findingId);
        $finding = app(DuplicateFinding::class)($source);
        $this->refreshExpectedVersion();
        $this->refreshListCounts();
        $this->selectFinding((int) $finding->getKey());
    }

    public function deleteFinding(int $findingId): void
    {
        $finding = $this->assessmentRecord()->findings()->findOrFail($findingId);
        app(DeleteFinding::class)($finding);
        $this->refreshExpectedVersion();
        $this->refreshListCounts();

        if ($this->selectedFindingId === $findingId) {
            $this->selectedFindingId = null;
            $this->findingData = [];
        }

        Notification::make()->success()->title(__('assestme.workspace.list.deleted'))->send();
    }

    /** @param array<int|string> $order */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->canReorderFindings()) {
            return;
        }

        $ids = array_map(static fn (int|string $id): int => (int) $id, array_values($order));
        $this->expectedVersion = app(ReorderFindings::class)($this->assessmentRecord(), $ids);
        $this->assessmentRecord()->setAttribute('lock_version', $this->expectedVersion);
        $this->saveStatus = self::STATUS_SAVED;
    }

    public function canReorderFindings(): bool
    {
        $hasActiveFilters = collect($this->tableFilters ?? [])->flatten()->contains(
            static fn (mixed $value): bool => is_bool($value) ? $value : filled($value),
        );

        return ! $this->isWorkspaceReadOnly() && blank($this->tableSearch) && ! $hasActiveFilters;
    }

    public function totalFindings(): int
    {
        return $this->totalFindingsCount ??= $this->assessmentRecord()->findings()->count();
    }

    public function reportFindings(): int
    {
        return $this->reportFindingsCount ??= $this->assessmentRecord()->findings()->where('include_in_report', true)->count();
    }

    public function saveAssessmentDetails(): void
    {
        if ($this->isWorkspaceReadOnly() || $this->saveStatus === self::STATUS_CONFLICT) {
            return;
        }

        $this->saveStatus = self::STATUS_SAVING;
        $rawState = $this->form->getRawState();
        $state = is_array($rawState) ? $rawState : $rawState->toArray();
        $payload = ['assessment' => [
            'title' => (string) ($state['title'] ?? ''),
            'assessment_date' => (string) ($state['assessment_date'] ?? ''),
            'report_title_override' => $state['report_title_override'] ?? null,
            'scope_type' => $state['scope_type'] ?? ScopeType::Organization->value,
            'scope_description' => $state['scope_description'] ?? null,
            'introduction' => $state['introduction'] ?? null,
            'executive_summary' => $state['executive_summary'] ?? null,
            'methodology_notes' => $state['methodology_notes'] ?? null,
            'site_ids' => is_array($state['site_ids'] ?? null) ? $state['site_ids'] : [],
        ]];

        try {
            $request = new WorkspaceSaveData(
                requestId: (string) Str::uuid(),
                expectedVersion: $this->expectedVersion,
                tabId: $this->tabId,
                payload: $payload,
                payloadSha256: WorkspaceSaveData::hashPayload($payload),
            );
            $result = app(SaveAssessmentWorkspace::class)($this->assessmentRecord(), $request);
            $this->expectedVersion = $result->appliedVersion;
            $this->assessmentRecord()->setAttribute('lock_version', $result->appliedVersion);
            $this->saveStatus = self::STATUS_SAVED;
            Notification::make()->success()->title(__('assestme.workspace.saved_notification'))->send();
        } catch (AssessmentVersionConflict) {
            $this->saveStatus = self::STATUS_CONFLICT;
            $this->saveError = __('assestme.workspace.errors.conflict');
        } catch (ValidationException $exception) {
            $this->saveStatus = self::STATUS_ERROR;
            $this->saveError = __('assestme.workspace.errors.validation');
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('data.'.str($key)->replaceStart('payload.', ''), $message);
                }
            }
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
        }
    }

    public function save(bool $shouldRedirect = false, bool $shouldSendSavedNotification = true): void
    {
        $this->saveAssessmentDetails();
    }

    public function getSaveStatusLabel(): string
    {
        return __('assestme.workspace.status.'.$this->saveStatus);
    }

    public function getTitle(): string
    {
        return __('assestme.workspace.title');
    }

    public function isWorkspaceReadOnly(): bool
    {
        return $this->assessmentRecord()->status !== AssessmentStatus::Draft;
    }

    public function assessmentRecord(): Assessment
    {
        $record = $this->getRecord();
        if (! $record instanceof Assessment) {
            throw new \LogicException('The workspace record must be an assessment.');
        }

        return $record;
    }

    public function selectedFinding(): ?Finding
    {
        if ($this->selectedFindingId === null) {
            return null;
        }

        return $this->assessmentRecord()->findings()
            ->with(['category', 'priorityLevel', 'consequenceLevel', 'likelihoodLevel', 'solutions', 'evidences', 'tags', 'sites', 'assets'])
            ->find($this->selectedFindingId);
    }

    public function selectedFindingPosition(): ?int
    {
        if ($this->selectedFindingId === null) {
            return null;
        }

        $ids = $this->assessmentRecord()->findings()->pluck('id')->values();
        $index = $ids->search($this->selectedFindingId);

        return is_int($index) ? $index + 1 : null;
    }

    /** @return list<string> */
    public function selectedFindingCompleteness(): array
    {
        $finding = $this->selectedFinding();

        return $finding instanceof Finding ? app(AssessFindingCompleteness::class)($finding) : [];
    }

    public function summaryPreview(): string
    {
        return __('assestme.workspace.summary_preview', [
            'client' => $this->assessmentRecord()->client->displayName(),
            'title' => $this->assessmentRecord()->report_title_override ?: $this->assessmentRecord()->title,
            'findings' => $this->assessmentRecord()->findings()->where('include_in_report', true)->count(),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label(__('assestme.workspace.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->assessmentRecord()->status === AssessmentStatus::Draft)
                ->action(function (): void {
                    $assessment = app(CompleteAssessment::class)($this->assessmentRecord());
                    $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $assessment]), navigate: false);
                }),
            Action::make('reopen')
                ->label(__('assestme.workspace.reopen'))
                ->icon('heroicon-o-lock-open')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->assessmentRecord()->status !== AssessmentStatus::Draft)
                ->action(function (): void {
                    $assessment = app(ReopenAssessment::class)($this->assessmentRecord());
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
                    return;
                }

                try {
                    $report = $this->assessmentRecord()->generatedReports()->findOrFail($reportId);
                    $operation = app(DeleteGeneratedReport::class)->handle($report);
                    Notification::make()
                        ->{($operation->status === DeletionOperationStatus::CleanupFailed) ? 'warning' : 'success'}()
                        ->title($operation->status === DeletionOperationStatus::CleanupFailed
                            ? __('assestme.reports.delete.cleanup_pending')
                            : __('assestme.reports.delete.deleted'))
                        ->send();
                } catch (Throwable $exception) {
                    Log::error('Generated report permanent deletion failed.', ['exception' => $exception]);
                    Notification::make()->danger()->title(__('assestme.reports.delete.failed'))->send();
                }
            });
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
        return [...$data, 'site_ids' => $this->assessmentRecord()->sites()->pluck('sites.id')->all()];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AssessmentVersionConflict
     * @throws IdempotencyKeyMismatch
     * @throws LockTimeoutException
     */
    private function persistFindingPayload(Finding $finding, array $payload): FindingSaveResult
    {
        $request = new FindingSaveData(
            requestId: (string) Str::uuid(),
            expectedVersion: $this->expectedVersion,
            tabId: $this->tabId,
            payload: $payload,
            payloadSha256: FindingSaveData::hashPayload($payload),
        );
        $result = app(SaveFindingDetails::class)($finding, $request);
        $this->expectedVersion = $result->appliedVersion;
        $this->assessmentRecord()->setAttribute('lock_version', $result->appliedVersion);

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private function persistInline(Finding $finding, array $payload, string $attribute): mixed
    {
        $oldValue = $finding->getAttribute($attribute);
        try {
            $result = $this->persistFindingPayload($finding, $payload);
            $value = $result->finding->getAttribute($attribute);
            if ($this->selectedFindingId === (int) $finding->getKey()) {
                $this->findingData[$attribute] = $value instanceof \BackedEnum ? $value->value : $value;
            }
            $this->saveStatus = self::STATUS_SAVED;
            $this->refreshListCounts();

            return $value instanceof \BackedEnum ? $value->value : $value;
        } catch (AssessmentVersionConflict) {
            $this->saveStatus = self::STATUS_CONFLICT;
            $this->saveError = __('assestme.workspace.errors.conflict');
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
            Notification::make()->danger()->title(__('assestme.workspace.errors.persistence'))->send();
        }

        return $oldValue instanceof \BackedEnum ? $oldValue->value : $oldValue;
    }

    private function loadSelectedFinding(int $findingId, bool $closeWhenMissing = false): void
    {
        $finding = $this->assessmentRecord()->findings()->find($findingId);
        if (! $finding instanceof Finding) {
            if ($closeWhenMissing) {
                $this->selectedFindingId = null;
                $this->findingData = [];

                return;
            }

            throw new \InvalidArgumentException('The selected finding does not belong to the assessment.');
        }

        $this->selectedFindingId = $findingId;
        $data = FindingEditorSchema::data($finding);
        $this->findingPropertiesSchema()->model($finding)->fill($data);
        $this->findingEditorSchema()->model($finding)->fill($data);
        $this->saveStatus = self::STATUS_SAVED;
        $this->saveError = null;
        $this->resetErrorBag();
    }

    private function selectAdjacentFinding(int $direction): void
    {
        if ($this->selectedFindingId === null) {
            return;
        }
        if ($this->saveStatus === self::STATUS_UNSAVED) {
            $this->notifyUnsavedSelectionBlocked();

            return;
        }

        $ids = $this->assessmentRecord()->findings()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $index = array_search($this->selectedFindingId, $ids, true);
        if (! is_int($index) || ! isset($ids[$index + $direction])) {
            return;
        }

        $this->loadSelectedFinding($ids[$index + $direction]);
        $this->dispatch('assestme-finding-selected');
    }

    private function notifyUnsavedSelectionBlocked(): void
    {
        Notification::make()
            ->warning()
            ->title(__('assestme.workspace.inspector.unsaved_navigation'))
            ->body(__('assestme.workspace.inspector.unsaved_navigation_body'))
            ->send();
    }

    private function uploadedFile(mixed $upload, mixed $originalName): ?UploadedFile
    {
        if ($upload instanceof UploadedFile) {
            return $upload;
        }
        if (! is_string($upload) || ! Storage::disk('local')->exists($upload)) {
            return null;
        }

        return new UploadedFile(
            Storage::disk('local')->path($upload),
            is_string($originalName) ? $originalName : basename($upload),
            Storage::disk('local')->mimeType($upload),
            null,
            true,
        );
    }

    private function refreshExpectedVersion(): void
    {
        $version = (int) $this->assessmentRecord()->fresh()->lock_version;
        $this->expectedVersion = $version;
        $this->assessmentRecord()->setAttribute('lock_version', $version);
    }

    private function refreshListCounts(): void
    {
        $this->totalFindingsCount = null;
        $this->reportFindingsCount = null;
    }

    private function reportSaveFailure(Throwable $exception): void
    {
        $this->saveStatus = self::STATUS_ERROR;
        $this->saveError = __('assestme.workspace.errors.persistence');
        Log::error('Assessment workspace save failed.', [
            'assessment_id' => $this->assessmentRecord()->getKey(),
            'expected_version' => $this->expectedVersion,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    private function findingEditorSchema(): Schema
    {
        $schema = $this->getSchema('findingEditor');
        if (! $schema instanceof Schema) {
            throw new \LogicException('The finding editor schema is unavailable.');
        }

        return $schema;
    }

    private function findingPropertiesSchema(): Schema
    {
        $schema = $this->getSchema('findingProperties');
        if (! $schema instanceof Schema) {
            throw new \LogicException('The finding properties schema is unavailable.');
        }

        return $schema;
    }

    private function generatePdf(): void
    {
        try {
            $report = app(GenerateAssessmentPdf::class)($this->assessmentRecord());
            Notification::make()->success()->title(__('assestme.reports.generated'))->body($report->file_name)->send();
            $this->redirect(route('generated-reports.download', $report), navigate: false);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('assestme.reports.errors.generation'))->body(collect($exception->errors())->flatten()->join(' '))->persistent()->send();
        } catch (Throwable $exception) {
            Log::error('Assessment PDF generation failed.', ['assessment_id' => $this->assessmentRecord()->getKey(), 'exception' => $exception]);
            Notification::make()->danger()->title(__('assestme.reports.errors.generation'))->body(__('assestme.reports.errors.retry'))->persistent()->send();
        }
    }

    private function generateWorkbook(bool $includeExcludedFindings): void
    {
        try {
            $report = app(GenerateAssessmentWorkbook::class)($this->assessmentRecord(), $includeExcludedFindings);
            Notification::make()->success()->title(__('assestme.reports.generated_xlsx'))->body($report->file_name)->send();
            $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $this->assessmentRecord()]), navigate: false);
        } catch (ValidationException $exception) {
            Notification::make()->danger()->title(__('assestme.reports.errors.generation_xlsx'))->body(collect($exception->errors())->flatten()->join(' '))->persistent()->send();
        } catch (Throwable $exception) {
            Log::error('Assessment XLSX generation failed.', ['assessment_id' => $this->assessmentRecord()->getKey(), 'exception' => $exception]);
            Notification::make()->danger()->title(__('assestme.reports.errors.generation_xlsx'))->body(__('assestme.reports.errors.retry_xlsx'))->persistent()->send();
        }
    }
}
