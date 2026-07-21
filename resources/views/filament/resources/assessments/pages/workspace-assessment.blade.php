<x-filament-panels::page>
    <x-filament::tabs label="{{ __('assestme.workspace.title') }}">
        @foreach ([
            'findings' => __('assestme.workspace.tabs.findings'),
            'assessment-details' => __('assestme.workspace.tabs.assessment_details'),
            'summary' => __('assestme.workspace.tabs.summary_preview'),
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
                        <details class="assestme-workbench-properties" open data-assestme-workbench-properties>
                            <summary class="assestme-workbench-properties__header">
                                <span>
                                    <span class="assestme-workbench-properties__eyebrow">{{ __('assestme.workspace.properties.eyebrow') }}</span>
                                    <strong>{{ __('assestme.workspace.properties.label') }}</strong>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-down" class="assestme-workbench-properties__chevron" />
                            </summary>
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
                        </details>

                        <footer class="assestme-workbench-footer">
                            <div>
                                @include('filament.workspace-save-status', [
                                    'status' => $saveStatus,
                                    'label' => $this->getSaveStatusLabel(),
                                ])
                                @if ($saveError)
                                    <p class="assestme-workbench-footer__error">{{ $saveError }}</p>
                                @endif
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
        <form wire:submit="saveAssessmentDetails">
            {{ $this->form }}
            @if (! $this->isWorkspaceReadOnly())
                <div class="assestme-assessment-save">
                    <x-filament::button type="submit" data-dusk="save-assessment">
                        {{ __('assestme.workspace.explicit_save') }}
                    </x-filament::button>
                </div>
            @endif
        </form>
    @elseif ($activeWorkspaceTab === 'summary')
        <x-filament::section>
            {{ $this->summaryPreview() }}
        </x-filament::section>
    @else
        <x-filament::section>
            @include('filament.generated-report-history', [
                'reports' => $this->assessmentRecord()->generatedReports()->latest('generated_at')->get(),
                'timezone' => app(\App\Settings\GeneralSettings::class)->timezone,
            ])
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
