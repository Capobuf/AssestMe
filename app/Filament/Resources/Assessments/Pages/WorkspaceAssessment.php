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
use App\Actions\Assessments\SaveFindingAsTemplate;
use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Assessments\UpdateTemplateFromFinding;
use App\Actions\Evidence\InspectEvidenceUpload;
use App\Actions\Reports\DeleteGeneratedReport;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Data\Assessments\FindingSaveData;
use App\Data\Assessments\FindingSaveResult;
use App\Data\Assessments\ReorderFindingsData;
use App\Data\Assessments\WorkspaceSaveData;
use App\Data\Evidence\PendingEvidenceFileData;
use App\Data\Evidence\PendingEvidenceUrlData;
use App\Data\Templates\FindingTemplateSyncResult;
use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\ScopeType;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Schemas\AssessmentWorkspaceForm;
use App\Filament\Resources\Assessments\Schemas\FindingEditorSchema;
use App\Filament\Resources\Assessments\Tables\AssessmentFindingsTable;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Services\Templates\FindRelatedFindingTemplates;
use App\Settings\FattureInCloudSettings;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Illuminate\View\View;
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

    public string $findingSaveStatus = self::STATUS_SAVED;

    public string $assessmentSaveStatus = self::STATUS_SAVED;

    public ?string $saveError = null;

    public ?string $saveErrorField = null;

    public ?string $pendingFindingRequestId = null;

    public ?string $pendingAssessmentRequestId = null;

    public ?string $pendingReorderRequestId = null;

    public ?int $totalFindingsCount = null;

    public ?int $reportFindingsCount = null;

    /** @var array<string, mixed> */
    public array $findingData = [];

    /** @var array<string, mixed> */
    public array $templateLearningPreview = [];

    #[Url(as: 'finding', history: true)]
    public ?int $selectedFindingId = null;

    #[Url(as: 'workspace-tab', history: true)]
    public string $activeWorkspaceTab = 'findings';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->expectedVersion = (int) $this->assessmentRecord()->lock_version;
        $this->tabId = (string) Str::uuid();
        $this->pendingReorderRequestId = (string) Str::uuid();

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
            ->with(['category', 'priorityLevel', 'sites', 'assets', 'solutions:id,finding_id', 'sourceTemplate'])
            ->withCount(['solutions', 'evidences'])
            ->orderBy('sort_order');
    }

    public function updatedFindingData(): void
    {
        if ($this->findingSaveStatus !== self::STATUS_CONFLICT) {
            $this->findingSaveStatus = self::STATUS_UNSAVED;
            $this->saveStatus = self::STATUS_UNSAVED;
            $this->saveError = null;
            $this->saveErrorField = null;
        }
    }

    public function updatedData(): void
    {
        if ($this->assessmentSaveStatus !== self::STATUS_CONFLICT) {
            $this->assessmentSaveStatus = self::STATUS_UNSAVED;
            if ($this->activeWorkspaceTab === 'assessment-details') {
                $this->saveStatus = self::STATUS_UNSAVED;
                $this->saveError = null;
                $this->saveErrorField = null;
            }
        }
    }

    public function setWorkspaceTab(string $tab): void
    {
        if (! in_array($tab, ['findings', 'assessment-details', 'generated-files'], true)
            || $tab === $this->activeWorkspaceTab
            || ! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $this->activeWorkspaceTab = $tab;
        $this->syncActiveSaveStatus();
    }

    public function selectFinding(int $findingId): void
    {
        if ($this->selectedFindingId === $findingId) {
            return;
        }
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $this->loadSelectedFinding($findingId);
        $this->dispatch('assestme-finding-selected');
    }

    public function closeInspector(): void
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $this->selectedFindingId = null;
        $this->findingData = [];
        $this->findingSaveStatus = self::STATUS_SAVED;
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

    public function saveFinding(bool $moveNext = false, bool $silent = false): bool
    {
        $finding = $this->selectedFinding();
        if (! $finding instanceof Finding || $this->isWorkspaceReadOnly() || $this->findingSaveStatus === self::STATUS_CONFLICT) {
            return false;
        }

        $this->findingSaveStatus = self::STATUS_SAVING;
        $this->saveStatus = self::STATUS_SAVING;
        $this->saveError = null;
        $this->saveErrorField = null;
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
            $evidenceFiles = $this->pendingEvidenceFiles($uploads, $originalNames);
            $evidenceUrlData = $evidenceUrl === '' ? null : new PendingEvidenceUrlData(
                title: $evidenceTitle,
                url: $evidenceUrl,
                includeInReport: true,
            );
            $aggregatePayload = FindingSaveData::aggregatePayload($payload, $evidenceFiles, $evidenceUrlData);
            $requestId = $this->resolveClientRequestId($this->pendingFindingRequestId);
            $result = $this->persistFindingPayload(
                $finding,
                $aggregatePayload,
                $requestId,
                $evidenceFiles,
                $evidenceUrlData,
            );
            $finding = $result->finding;

            Storage::disk('local')->delete(array_values(array_filter($uploads, 'is_string')));

            $this->loadSelectedFinding((int) $finding->getKey());
            $this->refreshListCounts();
            $this->findingSaveStatus = self::STATUS_SAVED;
            $this->saveStatus = self::STATUS_SAVED;

            if (! $silent) {
                Notification::make()
                    ->success()
                    ->title(__('assestme.workspace.inspector.saved'))
                    ->send();
            }

            if ($moveNext) {
                $this->selectAdjacentFinding(1);
            }

            $this->pendingFindingRequestId = null;
            $this->dispatch(
                'assestme-server-save-confirmed',
                kind: 'finding',
                assessmentId: (int) $this->assessmentRecord()->getKey(),
                findingId: (int) $finding->getKey(),
                requestId: $requestId,
                appliedVersion: $result->appliedVersion,
            );

            return true;
        } catch (AssessmentVersionConflict) {
            $this->findingSaveStatus = self::STATUS_CONFLICT;
            $this->saveStatus = self::STATUS_CONFLICT;
            $this->saveError = __('assestme.workspace.errors.conflict');
        } catch (ValidationException $exception) {
            $this->findingSaveStatus = self::STATUS_ERROR;
            $this->saveStatus = self::STATUS_ERROR;
            $this->saveError = __('assestme.workspace.errors.validation');
            $firstErrorPath = null;
            foreach ($exception->errors() as $key => $messages) {
                $errorPath = 'findingData.'.$key;
                $firstErrorPath ??= $errorPath;
                foreach ($messages as $message) {
                    $this->addError($errorPath, $message);
                }
            }
            $this->saveErrorField = $firstErrorPath;
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
        }

        return false;
    }

    public function saveFindingAndNext(): void
    {
        $this->saveFinding(moveNext: true);
    }

    public function updateInlineStatus(Finding $finding, string $status): string
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return $finding->status->value;
        }

        $payload = FindingEditorSchema::data($finding);
        $payload['status'] = $status;

        return $this->persistInline($finding, $payload, 'status');
    }

    public function updateInlineReportInclusion(Finding $finding, bool $included): bool
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return $finding->include_in_report;
        }

        $payload = FindingEditorSchema::data($finding);
        $payload['include_in_report'] = $included;

        return (bool) $this->persistInline($finding, $payload, 'include_in_report');
    }

    public function createBlankFinding(): void
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $finding = app(CreateBlankFinding::class)($this->assessmentRecord());
        $this->refreshExpectedVersion();
        $this->refreshListCounts();
        $this->selectFinding((int) $finding->getKey());
    }

    public function createFromTemplate(int $templateId): void
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $template = FindingTemplate::query()->findOrFail($templateId);
        $finding = app(CopyTemplateToAssessment::class)($this->assessmentRecord(), $template);
        $this->refreshExpectedVersion();
        $this->refreshListCounts();
        $this->selectFinding((int) $finding->getKey());
    }

    public function duplicateFinding(int $findingId): void
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $source = $this->assessmentRecord()->findings()->findOrFail($findingId);
        $finding = app(DuplicateFinding::class)($source);
        $this->refreshExpectedVersion();
        $this->refreshListCounts();
        $this->selectFinding((int) $finding->getKey());
    }

    public function deleteFinding(int $findingId): void
    {
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

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

    public function prepareSaveFindingAsTemplate(int $findingId): bool
    {
        if ($this->isWorkspaceReadOnly() || ! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return false;
        }

        try {
            $finding = $this->assessmentRecord()->findings()->with('solutions')->findOrFail($findingId);
            $related = app(FindRelatedFindingTemplates::class)->forFinding($finding);
            $mode = $related['exact'] instanceof FindingTemplate
                ? 'exact'
                : ($related['similar'] === [] ? 'new' : 'similar');
            $candidates = $related['exact'] instanceof FindingTemplate
                ? [$this->templateCandidate($related['exact'])]
                : array_map(
                    fn (array $candidate): array => $this->templateCandidate($candidate['template']),
                    $related['similar'],
                );

            $this->templateLearningPreview = [
                'mode' => $mode,
                'finding_id' => $findingId,
                'new_lineage' => $finding->source_template_id !== null,
                'priority_override' => $finding->priority_is_overridden,
                'candidates' => $candidates,
            ];

            return true;
        } catch (Throwable $exception) {
            $this->reportTemplateLearningFailure($exception);

            return false;
        }
    }

    public function applySaveFindingAsTemplate(int $findingId): void
    {
        try {
            $finding = $this->assessmentRecord()->findings()->findOrFail($findingId);
            if (($this->templateLearningPreview['mode'] ?? null) === 'exact') {
                $candidateId = (int) ($this->templateLearningPreview['candidates'][0]['id'] ?? 0);
                $template = FindingTemplate::query()->findOrFail($candidateId);
                $result = app(SaveFindingAsTemplate::class)->linkExact($finding, $template, $this->expectedVersion);
            } else {
                $result = app(SaveFindingAsTemplate::class)->create($finding, $this->expectedVersion);
            }

            $this->acceptTemplateLearningResult($result);
        } catch (Throwable $exception) {
            $this->reportTemplateLearningFailure($exception);
        }
    }

    public function prepareUpdateSourceTemplate(int $findingId): bool
    {
        if ($this->isWorkspaceReadOnly() || ! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return false;
        }

        $template = null;
        try {
            $finding = $this->assessmentRecord()->findings()->with('solutions')->findOrFail($findingId);
            $template = FindingTemplate::query()->with(['category', 'solutions'])->findOrFail($finding->source_template_id);
            $this->templateLearningPreview = [
                'mode' => 'update',
                'finding_id' => $findingId,
                'priority_override' => $finding->priority_is_overridden,
                'candidates' => [$this->templateCandidate($template)],
                'diff' => app(UpdateTemplateFromFinding::class)->preview($finding),
            ];

            return true;
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?? __('assestme.template_learning.errors.operation_failed');
            $notification = Notification::make()
                ->danger()
                ->title($message);
            if ($template instanceof FindingTemplate) {
                $notification->actions([
                    Action::make('open_template')
                        ->label(__('assestme.template_learning.actions.open_template'))
                        ->url(FindingTemplateResource::getUrl('edit', ['record' => $template]))
                        ->openUrlInNewTab(),
                ]);
            }
            $notification->persistent()->send();

            return false;
        } catch (Throwable $exception) {
            $this->reportTemplateLearningFailure($exception);

            return false;
        }
    }

    public function applyUpdateSourceTemplate(int $findingId): void
    {
        try {
            $finding = $this->assessmentRecord()->findings()->findOrFail($findingId);
            $result = app(UpdateTemplateFromFinding::class)->handle($finding, $this->expectedVersion);
            $this->acceptTemplateLearningResult($result);
        } catch (Throwable $exception) {
            $this->reportTemplateLearningFailure($exception);
        }
    }

    public function templateLearningPreviewView(): View
    {
        return view('filament.resources.assessments.template-learning-preview', [
            'preview' => $this->templateLearningPreview,
        ]);
    }

    public function templateLearningHeading(): string
    {
        return __('assestme.template_learning.headings.'.match ($this->templateLearningPreview['mode'] ?? 'new') {
            'exact' => 'exact',
            'similar' => 'similar',
            'update' => 'update',
            default => ($this->templateLearningPreview['new_lineage'] ?? false) ? 'save_new' : 'save',
        });
    }

    public function templateLearningSubmitLabel(): string
    {
        return __('assestme.template_learning.actions.'.match ($this->templateLearningPreview['mode'] ?? 'new') {
            'exact' => 'link_exact',
            'similar' => 'save_anyway',
            'update' => 'update_source',
            default => ($this->templateLearningPreview['new_lineage'] ?? false) ? 'save_new' : 'save',
        });
    }

    /** @param array<int|string> $order */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->canReorderFindings() || ! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $ids = array_map(static fn (int|string $id): int => (int) $id, array_values($order));
        $requestId = $this->pendingReorderRequestId ?? (string) Str::uuid();
        $request = new ReorderFindingsData(
            requestId: $requestId,
            expectedVersion: $this->expectedVersion,
            orderedFindingIds: $ids,
            payloadSha256: ReorderFindingsData::hashPayload($ids),
        );
        $this->saveStatus = self::STATUS_SAVING;

        try {
            $this->expectedVersion = app(ReorderFindings::class)($this->assessmentRecord(), $request);
            $this->assessmentRecord()->setAttribute('lock_version', $this->expectedVersion);
            $this->pendingReorderRequestId = (string) Str::uuid();
            $this->saveStatus = self::STATUS_SAVED;
            $this->saveError = null;
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
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
        }
    }

    public function canReorderFindings(): bool
    {
        return ! $this->isWorkspaceReadOnly() && blank($this->tableSearch);
    }

    public function totalFindings(): int
    {
        return $this->totalFindingsCount ??= $this->assessmentRecord()->findings()->count();
    }

    public function reportFindings(): int
    {
        return $this->reportFindingsCount ??= $this->assessmentRecord()->findings()->where('include_in_report', true)->count();
    }

    public function saveAssessmentDetails(bool $silent = false): bool
    {
        if ($this->isWorkspaceReadOnly() || $this->assessmentSaveStatus === self::STATUS_CONFLICT) {
            return false;
        }

        $this->assessmentSaveStatus = self::STATUS_SAVING;
        $this->saveStatus = self::STATUS_SAVING;
        $this->saveErrorField = null;
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
            $requestId = $this->resolveClientRequestId($this->pendingAssessmentRequestId);
            $request = new WorkspaceSaveData(
                requestId: $requestId,
                expectedVersion: $this->expectedVersion,
                tabId: $this->tabId,
                payload: $payload,
                payloadSha256: WorkspaceSaveData::hashPayload($payload),
            );
            $result = app(SaveAssessmentWorkspace::class)($this->assessmentRecord(), $request);
            $this->expectedVersion = $result->appliedVersion;
            $this->assessmentRecord()->setAttribute('lock_version', $result->appliedVersion);
            $this->assessmentSaveStatus = self::STATUS_SAVED;
            $this->saveStatus = self::STATUS_SAVED;
            if (! $silent) {
                Notification::make()->success()->title(__('assestme.workspace.saved_notification'))->send();
            }
            $this->pendingAssessmentRequestId = null;
            $this->dispatch(
                'assestme-server-save-confirmed',
                kind: 'assessment',
                assessmentId: (int) $this->assessmentRecord()->getKey(),
                findingId: null,
                requestId: $requestId,
                appliedVersion: $result->appliedVersion,
            );

            return true;
        } catch (AssessmentVersionConflict) {
            $this->assessmentSaveStatus = self::STATUS_CONFLICT;
            $this->saveStatus = self::STATUS_CONFLICT;
            $this->saveError = __('assestme.workspace.errors.conflict');
        } catch (ValidationException $exception) {
            $this->assessmentSaveStatus = self::STATUS_ERROR;
            $this->saveStatus = self::STATUS_ERROR;
            $this->saveError = __('assestme.workspace.errors.validation');
            $firstErrorPath = null;
            foreach ($exception->errors() as $key => $messages) {
                $stateKey = str($key)
                    ->replaceStart('payload.assessment.', '')
                    ->replaceStart('assessment.', '');
                $errorPath = 'data.'.$stateKey;
                $firstErrorPath ??= $errorPath;
                foreach ($messages as $message) {
                    $this->addError($errorPath, $message);
                }
            }
            $this->saveErrorField = $firstErrorPath;
        } catch (Throwable $exception) {
            $this->reportSaveFailure($exception);
        }

        return false;
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
            ->with(['category', 'priorityLevel', 'consequenceLevel', 'likelihoodLevel', 'solutions', 'evidences', 'sites', 'assets', 'sourceTemplate'])
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

    /** @return array<Action | ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_fatture_in_cloud_quote')
                ->label(__('assestme.fatture_in_cloud.composer.action'))
                ->icon('heroicon-o-document-currency-euro')
                ->visible(fn (): bool => ! $this->isWorkspaceReadOnly())
                ->disabled(fn (): bool => ! $this->fattureInCloudReady())
                ->tooltip(fn (): ?string => $this->fattureInCloudReady()
                    ? null
                    : __('assestme.fatture_in_cloud.composer.not_ready'))
                ->action(function (): void {
                    $this->openFattureInCloudQuoteComposer();
                }),
            Action::make('complete')
                ->label(__('assestme.workspace.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->outlined()
                ->extraAttributes([
                    'class' => 'assestme-complete-action',
                    'data-dusk' => 'complete-assessment',
                ])
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->assessmentRecord()->status === AssessmentStatus::Draft)
                ->action(function (): void {
                    if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
                        return;
                    }

                    $assessment = app(CompleteAssessment::class)($this->assessmentRecord());
                    $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $assessment]), navigate: false);
                }),
            Action::make('reopen')
                ->label(__('assestme.workspace.reopen'))
                ->icon('heroicon-o-lock-open')
                ->color('gray')
                ->outlined()
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->assessmentRecord()->status !== AssessmentStatus::Draft)
                ->action(function (): void {
                    $assessment = app(ReopenAssessment::class)($this->assessmentRecord());
                    $this->redirect(AssessmentResource::getUrl('workspace', ['record' => $assessment]), navigate: false);
                }),
            ActionGroup::make([
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
            ])
                ->label(__('assestme.workspace.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->outlined()
                ->button()
                ->extraAttributes([
                    'class' => 'assestme-export-action',
                    'data-dusk' => 'export-menu',
                ]),
        ];
    }

    public function openFattureInCloudQuoteComposer(): void
    {
        if ($this->isWorkspaceReadOnly() || ! $this->fattureInCloudReady()
            || ! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

        $this->redirect(AssessmentResource::getUrl('create-fatture-in-cloud-quote', [
            'record' => $this->assessmentRecord(),
        ]), navigate: false);
    }

    private function fattureInCloudReady(): bool
    {
        $settings = app(FattureInCloudSettings::class);

        return is_string($settings->company_id) && $settings->company_id !== ''
            && is_string($settings->encrypted_refresh_token) && $settings->encrypted_refresh_token !== ''
            && is_string($settings->default_vat_type_id) && $settings->default_vat_type_id !== '';
    }

    public function deleteGeneratedReportAction(): Action
    {
        return Action::make('deleteGeneratedReport')
            ->label(__('assestme.reports.delete.action'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->button()
            ->outlined()
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('assestme.reports.delete.heading'))
            ->modalDescription(__('assestme.reports.delete.description'))
            ->modalSubmitActionLabel(__('assestme.reports.delete.confirm'))
            ->extraAttributes(['data-dusk' => 'delete-generated-report'])
            ->action(function (array $arguments): void {
                if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
                    return;
                }

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
     * @param  list<PendingEvidenceFileData>  $evidenceFiles
     *
     * @throws AssessmentVersionConflict
     * @throws IdempotencyKeyMismatch
     * @throws LockTimeoutException
     */
    private function persistFindingPayload(
        Finding $finding,
        array $payload,
        ?string $requestId = null,
        array $evidenceFiles = [],
        ?PendingEvidenceUrlData $evidenceUrl = null,
    ): FindingSaveResult {
        $resolvedRequestId = $this->resolveClientRequestId($requestId);
        if (! array_key_exists('_evidence', $payload)) {
            $payload = FindingSaveData::aggregatePayload($payload, $evidenceFiles, $evidenceUrl);
        }

        $request = new FindingSaveData(
            requestId: $resolvedRequestId,
            expectedVersion: $this->expectedVersion,
            tabId: $this->tabId,
            payload: $payload,
            payloadSha256: FindingSaveData::hashPayload($payload),
            evidenceFiles: $evidenceFiles,
            evidenceUrl: $evidenceUrl,
        );
        $result = app(SaveFindingDetails::class)($finding, $request);
        $this->expectedVersion = $result->appliedVersion;
        $this->assessmentRecord()->setAttribute('lock_version', $result->appliedVersion);

        return $result;
    }

    private function resolveClientRequestId(?string $requestId): string
    {
        if ($requestId === null) {
            return (string) Str::uuid();
        }

        if (! Str::isUuid($requestId)) {
            throw ValidationException::withMessages([
                'request_id' => __('assestme.workspace.errors.invalid_request'),
            ]);
        }

        return $requestId;
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
            $this->findingSaveStatus = self::STATUS_SAVED;
            $this->saveStatus = self::STATUS_SAVED;
            $this->refreshListCounts();

            return $value instanceof \BackedEnum ? $value->value : $value;
        } catch (AssessmentVersionConflict) {
            $this->findingSaveStatus = self::STATUS_CONFLICT;
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
        $this->findingSaveStatus = self::STATUS_SAVED;
        $this->saveStatus = self::STATUS_SAVED;
        $this->saveError = null;
        $this->saveErrorField = null;
        $this->resetErrorBag();
    }

    private function selectAdjacentFinding(int $direction): void
    {
        if ($this->selectedFindingId === null) {
            return;
        }
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
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

    /**
     * @param  array<int|string, mixed>  $uploads
     * @param  array<int|string, mixed>  $originalNames
     * @return list<PendingEvidenceFileData>
     */
    private function pendingEvidenceFiles(array $uploads, array $originalNames): array
    {
        $files = [];
        foreach ($uploads as $key => $upload) {
            $uploadedFile = $this->uploadedFile($upload, $originalNames[$key] ?? null);
            if (! $uploadedFile instanceof UploadedFile) {
                throw ValidationException::withMessages([
                    "evidence_uploads.{$key}" => __('validation.file', ['attribute' => 'file']),
                ]);
            }
            $files[] = app(InspectEvidenceUpload::class)->handle($uploadedFile);
        }

        return $files;
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

    /** @return array{id:int,title:string,category:string,disabled:bool,url:string} */
    private function templateCandidate(FindingTemplate $template): array
    {
        $template->loadMissing('category');

        return [
            'id' => (int) $template->getKey(),
            'title' => $template->title,
            'category' => $template->category->name,
            'disabled' => ! $template->is_enabled,
            'url' => FindingTemplateResource::getUrl('edit', ['record' => $template]),
        ];
    }

    private function acceptTemplateLearningResult(FindingTemplateSyncResult $result): void
    {
        $this->expectedVersion = $result->appliedVersion;
        $this->assessmentRecord()->setAttribute('lock_version', $result->appliedVersion);
        if ($this->selectedFindingId === (int) $result->finding->getKey()) {
            $this->loadSelectedFinding((int) $result->finding->getKey());
        }
        $this->templateLearningPreview = [];
        Notification::make()
            ->success()
            ->title(__('assestme.template_learning.outcomes.'.$result->outcome))
            ->send();
    }

    private function reportTemplateLearningFailure(Throwable $exception): void
    {
        if ($exception instanceof AssessmentVersionConflict) {
            $this->findingSaveStatus = self::STATUS_CONFLICT;
            $this->saveStatus = self::STATUS_CONFLICT;
            $message = __('assestme.workspace.errors.conflict');
        } elseif ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first()
                ?? __('assestme.template_learning.errors.operation_failed');
        } else {
            $message = __('assestme.template_learning.errors.operation_failed');
            Log::error('Finding template learning operation failed.', [
                'assessment_id' => $this->assessmentRecord()->getKey(),
                'exception' => $exception,
            ]);
        }

        Notification::make()->danger()->title($message)->persistent()->send();
    }

    private function reportSaveFailure(Throwable $exception): void
    {
        if ($this->activeWorkspaceTab === 'assessment-details') {
            $this->assessmentSaveStatus = self::STATUS_ERROR;
        } else {
            $this->findingSaveStatus = self::STATUS_ERROR;
        }
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
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

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
        if (! $this->persistCurrentWorkspaceStateBeforeAction()) {
            return;
        }

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

    private function persistCurrentWorkspaceStateBeforeAction(): bool
    {
        if ($this->isWorkspaceReadOnly()) {
            return true;
        }

        if ($this->activeWorkspaceTab === 'findings' && $this->selectedFindingId !== null) {
            return match ($this->findingSaveStatus) {
                self::STATUS_SAVED => true,
                self::STATUS_UNSAVED, self::STATUS_ERROR => $this->saveFinding(silent: true),
                self::STATUS_SAVING, self::STATUS_CONFLICT => false,
                default => false,
            };
        }

        if ($this->activeWorkspaceTab === 'assessment-details') {
            return match ($this->assessmentSaveStatus) {
                self::STATUS_SAVED => true,
                self::STATUS_UNSAVED, self::STATUS_ERROR => $this->saveAssessmentDetails(silent: true),
                self::STATUS_SAVING, self::STATUS_CONFLICT => false,
                default => false,
            };
        }

        return true;
    }

    private function syncActiveSaveStatus(): void
    {
        $this->saveStatus = match ($this->activeWorkspaceTab) {
            'findings' => $this->findingSaveStatus,
            'assessment-details' => $this->assessmentSaveStatus,
            default => self::STATUS_SAVED,
        };
        $this->saveError = null;
    }
}
