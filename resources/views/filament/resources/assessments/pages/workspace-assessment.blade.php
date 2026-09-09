<x-filament-panels::page>
    <div
        class="assestme-workspace-context"
        data-assestme-workspace-context
        data-user-id="{{ auth()->id() }}"
        data-assessment-id="{{ $this->assessmentRecord()->getKey() }}"
        data-finding-id="{{ $selectedFindingId ?? '' }}"
        data-tab-id="{{ $tabId }}"
        data-expected-version="{{ $expectedVersion }}"
        data-read-only="{{ $this->isWorkspaceReadOnly() ? 'true' : 'false' }}"
        data-active-tab="{{ $activeWorkspaceTab }}"
        data-active-form="{{ $activeWorkspaceTab === 'findings' && $selectedFindingId ? 'finding' : ($activeWorkspaceTab === 'assessment-details' ? 'assessment' : '') }}"
        data-active-state-path="{{ $activeWorkspaceTab === 'findings' && $selectedFindingId ? 'findingData' : ($activeWorkspaceTab === 'assessment-details' ? 'data' : '') }}"
        data-validation-field="{{ $saveErrorField ?? '' }}"
    >
    <x-filament::tabs label="{{ __('assestme.workspace.title') }}">
        @foreach ([
            'findings' => __('assestme.workspace.tabs.findings'),
            'assessment-details' => __('assestme.workspace.tabs.assessment_details'),
            'generated-files' => __('assestme.workspace.tabs.generated_files'),
        ] as $tab => $label)
            <x-filament::tabs.item
                :active="$activeWorkspaceTab === $tab"
                wire:click="setWorkspaceTab('{{ $tab }}')"
                tag="button"
                type="button"
                class="assestme-workspace-tab {{ $activeWorkspaceTab === $tab ? 'is-active' : '' }}"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <section
        class="assestme-local-draft"
        data-assestme-local-draft
        data-dusk="local-draft-banner"
        data-draft-status="current"
        data-available-title-label="{{ __('assestme.workspace.drafts.available') }}"
        data-storage-error-title-label="{{ __('assestme.workspace.status.storage_error') }}"
        data-read-only-title-label="{{ __('assestme.workspace.drafts.read_only_title') }}"
        data-current-version-label="{{ __('assestme.workspace.drafts.version_current') }}"
        data-stale-version-label="{{ __('assestme.workspace.drafts.version_stale') }}"
        data-count-one-label="{{ __('assestme.workspace.drafts.count_one') }}"
        data-count-many-label="{{ __('assestme.workspace.drafts.count_many') }}"
        data-updated-at-label="{{ __('assestme.workspace.drafts.updated_at') }}"
        data-read-only-label="{{ __('assestme.workspace.drafts.read_only') }}"
        data-storage-unavailable-label="{{ __('assestme.workspace.drafts.storage_unavailable') }}"
        data-schema-incompatible-label="{{ __('assestme.workspace.drafts.schema_incompatible') }}"
        data-solution-structure-mismatch-label="{{ __('assestme.workspace.drafts.solution_structure_mismatch') }}"
        aria-labelledby="assestme-local-draft-title"
        aria-live="polite"
        hidden
    >
        <div class="assestme-local-draft__icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-circle-stack" />
        </div>

        <div class="assestme-local-draft__content">
            <h2 id="assestme-local-draft-title" data-assestme-local-draft-title>
                {{ __('assestme.workspace.drafts.available') }}
            </h2>
            <div class="assestme-local-draft__metadata">
                <p>
                    <strong>{{ __('assestme.workspace.drafts.updated_at') }}:</strong>
                    <time data-assestme-local-draft-updated-at>—</time>
                </p>
                <p data-assestme-local-draft-version>{{ __('assestme.workspace.drafts.version_current') }}</p>
                <p data-assestme-local-draft-count>{{ __('assestme.workspace.drafts.count_one', ['count' => 1]) }}</p>
            </div>
            <p class="assestme-local-draft__secondary">{{ __('assestme.workspace.drafts.device_only') }}</p>
            <p class="assestme-local-draft__secondary" data-assestme-draft-evidence-warning>
                {{ __('assestme.workspace.drafts.evidence_excluded') }}
            </p>
            <p class="assestme-local-draft__secondary">
                {{ __('assestme.workspace.drafts.solution_structure_limited') }}
            </p>
            <p
                class="assestme-local-draft__secondary"
                data-assestme-storage-persistence-note
                hidden
            >
                {{ __('assestme.workspace.drafts.storage_not_guaranteed') }}
            </p>
            <p class="assestme-local-draft__error" data-assestme-local-draft-error hidden>
                {{ __('assestme.workspace.drafts.storage_unavailable') }}
            </p>
            <p class="assestme-local-draft__pending">{{ __('assestme.workspace.drafts.server_unconfirmed') }}</p>
            @if ($this->isWorkspaceReadOnly())
                <p class="assestme-local-draft__read-only" data-assestme-local-draft-read-only>
                    {{ __('assestme.workspace.drafts.read_only') }}
                </p>
            @endif
        </div>

        <div class="assestme-local-draft__actions" data-assestme-local-draft-actions>
            <x-filament::button
                type="button"
                size="sm"
                data-dusk="restore-local-draft"
                :disabled="$this->isWorkspaceReadOnly()"
            >
                {{ __('assestme.workspace.drafts.restore') }}
            </x-filament::button>
            <x-filament::button
                type="button"
                size="sm"
                color="gray"
                outlined
                data-dusk="discard-local-draft"
            >
                {{ __('assestme.workspace.drafts.discard') }}
            </x-filament::button>
        </div>
    </section>

    @if ($activeWorkspaceTab === 'findings')
        <div class="assestme-findings-container" data-assestme-findings-container>
            <div
                class="assestme-findings-workspace {{ $selectedFindingId ? 'has-inspector' : '' }}"
                data-assestme-findings-workspace
                data-selected-finding="{{ $selectedFindingId }}"
            >
                <nav class="assestme-findings-list" aria-label="{{ __('assestme.workspace.navigator.label') }}">
                    <div class="assestme-findings-list__heading">
                        <div>
                            <span class="assestme-findings-list__eyebrow">{{ __('assestme.workspace.navigator.eyebrow') }}</span>
                            <h2>{{ __('assestme.workspace.navigator.label') }}</h2>
                        </div>
                        <div
                            class="assestme-findings-list__counters"
                            aria-label="{{ trans_choice('assestme.workspace.list.total_count', $this->totalFindings(), ['count' => $this->totalFindings()]) }}"
                            aria-live="polite"
                        >
                            {{ $this->totalFindings() }}
                        </div>
                    </div>
                    {{ $this->table }}
                </nav>

                @if ($selectedFindingId && ($finding = $this->selectedFinding()))
                    <form
                        wire:submit="saveFinding"
                        class="assestme-workbench-form"
                        aria-label="{{ __('assestme.workspace.editor.label') }}"
                        data-assestme-finding-inspector
                        data-assestme-draft-form="finding"
                        data-assestme-draft-state-path="findingData"
                    >
                        <header class="assestme-workbench-editor__header">
                            <div class="assestme-workbench-editor__heading">
                                <span class="assestme-workbench-editor__number">
                                    {{ str_pad((string) ($this->selectedFindingPosition() ?? 0), 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <div>
                                    <span class="assestme-workbench-editor__eyebrow">{{ __('assestme.workspace.editor.eyebrow') }}</span>
                                    <h2>{{ $finding->title ?: __('assestme.workspace.list.untitled') }}</h2>
                                    <p>
                                        {{ $finding->priorityLevel?->label ?? __('assestme.workspace.list.priority_missing') }}
                                        · {{ \App\Enums\FindingStatus::options()[$finding->status->value] }}
                                    </p>
                                </div>
                            </div>
                            <div class="assestme-workbench-editor__navigation">
                                <x-filament::icon-button
                                    icon="heroicon-o-chevron-left"
                                    wire:click="selectPreviousFinding"
                                    :label="__('assestme.workspace.inspector.previous')"
                                    data-dusk="finding-previous"
                                />
                                <x-filament::icon-button
                                    icon="heroicon-o-chevron-right"
                                    wire:click="selectNextFinding"
                                    :label="__('assestme.workspace.inspector.next')"
                                    data-dusk="finding-next"
                                />
                                <x-filament::icon-button
                                    icon="heroicon-o-x-mark"
                                    wire:click="closeInspector"
                                    :label="__('assestme.workspace.inspector.close')"
                                    data-dusk="finding-close"
                                />
                            </div>
                        </header>

                        <main class="assestme-workbench-editor" data-assestme-workbench-editor>
                            {{ $this->findingEditor }}
                        </main>

                        @php($completeness = $this->selectedFindingCompleteness())
                        <aside class="assestme-workbench-properties" data-assestme-workbench-properties>
                            <header class="assestme-workbench-properties__header">
                                <span>
                                    <span class="assestme-workbench-properties__eyebrow">{{ __('assestme.workspace.properties.eyebrow') }}</span>
                                    <strong>{{ __('assestme.workspace.properties.label') }}</strong>
                                </span>
                            </header>
                            <div class="assestme-workbench-properties__body">
                                <div class="assestme-workbench-completeness {{ $completeness === [] ? 'is-complete' : 'is-incomplete' }}">
                                    <div>
                                        <span>{{ __('assestme.workspace.properties.completeness') }}</span>
                                        <strong>
                                            {{ $completeness === [] ? __('assestme.workspace.list.complete') : __('assestme.workspace.list.incomplete') }}
                                        </strong>
                                    </div>
                                    @if ($completeness !== [])
                                        <ul>
                                            @foreach ($completeness as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                {{ $this->findingProperties }}
                            </div>
                        </aside>

                        <footer class="assestme-workbench-footer">
                            <div>
                                @include('filament.workspace-save-status', [
                                    'status' => $saveStatus,
                                    'label' => $this->getSaveStatusLabel(),
                                ])
                                <p class="assestme-workbench-footer__draft-note" data-assestme-evidence-draft-notice>
                                    {{ __('assestme.workspace.drafts.evidence_excluded') }}
                                </p>
                            </div>
                            @if (! $this->isWorkspaceReadOnly())
                                <div class="assestme-workbench-footer__actions">
                                    <x-filament::button type="submit" class="assestme-save-primary" data-dusk="save-finding">
                                        {{ __('assestme.workspace.inspector.save') }}
                                    </x-filament::button>
                                    <x-filament::button type="button" color="gray" outlined wire:click="saveFindingAndNext" data-dusk="save-finding-next">
                                        {{ __('assestme.workspace.inspector.save_next') }}
                                    </x-filament::button>
                                </div>
                            @endif
                        </footer>
                    </form>
                @else
                    <section class="assestme-workbench-empty" aria-label="{{ __('assestme.workspace.editor.empty') }}">
                        <x-filament::icon icon="heroicon-o-cursor-arrow-rays" />
                        <h2>{{ __('assestme.workspace.editor.empty') }}</h2>
                        <p>{{ __('assestme.workspace.editor.empty_description') }}</p>
                    </section>
                @endif
            </div>
        </div>
    @elseif ($activeWorkspaceTab === 'assessment-details')
        <form
            wire:submit="saveAssessmentDetails"
            data-assestme-draft-form="assessment"
            data-assestme-draft-state-path="data"
        >
            {{ $this->form }}
            <div class="assestme-assessment-save">
                @include('filament.workspace-save-status', [
                    'status' => $saveStatus,
                    'label' => $this->getSaveStatusLabel(),
                ])
                @if (! $this->isWorkspaceReadOnly())
                    <x-filament::button type="submit" data-dusk="save-assessment">
                        {{ __('assestme.workspace.explicit_save') }}
                    </x-filament::button>
                @endif
            </div>
        </form>
    @else
        <x-filament::section
            icon="heroicon-o-document-arrow-down"
            :heading="__('assestme.workspace.tabs.generated_files')"
            :description="__('assestme.workspace.generated_files_description')"
            class="assestme-generated-files-section"
        >
            @include('filament.generated-report-history', [
                'reports' => $this->assessmentRecord()->generatedReports()->latest('generated_at')->get(),
                'timezone' => app(\App\Settings\GeneralSettings::class)->timezone,
            ])
        </x-filament::section>
    @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
