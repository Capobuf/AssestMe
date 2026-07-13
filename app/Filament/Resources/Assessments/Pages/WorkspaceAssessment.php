<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Data\Assessments\WorkspaceSaveData;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Schemas\AssessmentWorkspaceForm;
use App\Models\Assessment;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class WorkspaceAssessment extends EditRecord
{
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
            Action::make('download_pdf')
                ->label(__('assestme.workspace.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->url(fn (): string => route('assessments.proof-pdf', $this->assessment())),
            Action::make('download_xlsx')
                ->label(__('assestme.workspace.download_xlsx'))
                ->icon('heroicon-o-table-cells')
                ->url(fn (): string => route('assessments.proof-xlsx', $this->assessment())),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(__('assestme.workspace.explicit_save'));
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
             *     assessment: array{title: string, assessment_date: string},
             *     findings: list<array<string, mixed>>
             * } $payload
             */
            $payload = [
                'assessment' => [
                    'title' => (string) ($state['title'] ?? ''),
                    'assessment_date' => (string) ($state['assessment_date'] ?? ''),
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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $record;
    }
}
