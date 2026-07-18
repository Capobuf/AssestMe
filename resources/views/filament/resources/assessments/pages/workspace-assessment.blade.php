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
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    @if ($activeWorkspaceTab === 'findings')
        <div
            class="assestme-findings-workspace {{ $selectedFindingId ? 'has-inspector' : '' }}"
            data-assestme-findings-workspace
            data-selected-finding="{{ $selectedFindingId }}"
        >
            <section class="assestme-findings-list" aria-label="{{ __('assestme.workspace.findings') }}">
                <div class="assestme-findings-list__counters" aria-live="polite">
                    <span>{{ trans_choice('assestme.workspace.list.total_count', $this->totalFindings(), ['count' => $this->totalFindings()]) }}</span>
                    <span>{{ trans_choice('assestme.workspace.list.report_count', $this->reportFindings(), ['count' => $this->reportFindings()]) }}</span>
                </div>
                {{ $this->table }}
            </section>

            @if ($selectedFindingId && ($finding = $this->selectedFinding()))
                <aside
                    class="assestme-finding-inspector"
                    aria-label="{{ __('assestme.workspace.inspector.label') }}"
                    data-assestme-finding-inspector
                >
                    <header class="assestme-finding-inspector__header">
                        <div class="assestme-finding-inspector__heading">
                            <span class="assestme-finding-inspector__number">
                                {{ str_pad((string) ($this->selectedFindingPosition() ?? 0), 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <div>
                                <h2>{{ $finding->title ?: __('assestme.workspace.list.untitled') }}</h2>
                                <p>
                                    {{ $finding->priorityLevel?->label ?? __('assestme.workspace.list.priority_missing') }}
                                    · {{ \App\Enums\FindingStatus::options()[$finding->status->value] }}
                                </p>
                            </div>
                        </div>
                        <div class="assestme-finding-inspector__navigation">
                            <x-filament::icon-button
                                icon="heroicon-o-chevron-up"
                                wire:click="selectPreviousFinding"
                                :label="__('assestme.workspace.inspector.previous')"
                                data-dusk="finding-previous"
                            />
                            <x-filament::icon-button
                                icon="heroicon-o-chevron-down"
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

                    <form wire:submit="saveFinding" class="assestme-finding-inspector__form">
                        <div class="assestme-finding-inspector__body">
                            {{ $this->findingEditor }}
                        </div>

                        <footer class="assestme-finding-inspector__footer">
                            <div>
                                @include('filament.workspace-save-status', [
                                    'status' => $saveStatus,
                                    'label' => $this->getSaveStatusLabel(),
                                ])
                                @if ($saveError)
                                    <p class="assestme-finding-inspector__error">{{ $saveError }}</p>
                                @endif
                            </div>
                            @if (! $this->isWorkspaceReadOnly())
                                <div class="assestme-finding-inspector__save-actions">
                                    <x-filament::button type="submit" data-dusk="save-finding">
                                        {{ __('assestme.workspace.inspector.save') }}
                                    </x-filament::button>
                                    <x-filament::button type="button" color="gray" wire:click="saveFindingAndNext" data-dusk="save-finding-next">
                                        {{ __('assestme.workspace.inspector.save_next') }}
                                    </x-filament::button>
                                </div>
                            @endif
                        </footer>
                    </form>
                </aside>
            @endif
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
