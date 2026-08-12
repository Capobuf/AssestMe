<x-filament-panels::page>
    <div class="assestme-fic-composer">
        @if (! $providerReady)
            <x-filament::section data-dusk="fic-not-ready">
                <x-slot name="heading">{{ __('assestme.fatture_in_cloud.states.configuration_missing_heading') }}</x-slot>
                <p>{{ __('assestme.fatture_in_cloud.composer.not_ready') }}</p>
                <x-filament::button class="mt-3" tag="a" :href="$this->settingsUrl()">
                    {{ __('assestme.fatture_in_cloud.composer.open_settings') }}
                </x-filament::button>
            </x-filament::section>
        @else
            <x-filament::section class="assestme-fic-composer__client-panel" data-dusk="fic-client-panel">
                <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.client_heading') }}</x-slot>

                @if ($clientResolutionFailed)
                    <div class="assestme-fic-composer__message" data-dusk="fic-client-error">
                        <p>{{ __('assestme.fatture_in_cloud.composer.client_resolution_failed') }}</p>
                        <x-filament::button wire:click="resolveClient" color="gray">
                            {{ __('assestme.fatture_in_cloud.composer.retry') }}
                        </x-filament::button>
                    </div>
                @elseif ($resolvedClientId)
                    <div class="assestme-fic-composer__resolved-client" data-dusk="fic-client-resolved">
                        <div class="assestme-fic-composer__client-identity">
                            <x-filament::icon class="assestme-fic-composer__client-icon" icon="heroicon-o-building-office-2" />
                            <strong>{{ __('assestme.fatture_in_cloud.composer.client_resolved', ['name' => $resolvedClientName]) }}</strong>
                        </div>
                        <span class="assestme-fic-composer__status assestme-fic-composer__status--success">
                            <x-filament::icon icon="heroicon-m-check-circle" />
                            {{ __('assestme.fatture_in_cloud.composer.client_resolved_status') }}
                        </span>
                    </div>
                @elseif (count($clientCandidates) > 0)
                    <div class="assestme-fic-composer__message" data-dusk="fic-client-candidates">
                        <p>{{ __('assestme.fatture_in_cloud.composer.choose_exact_client') }}</p>
                        @foreach ($clientCandidates as $candidate)
                            <div class="assestme-fic-composer__candidate">
                                <div>
                                    <strong>{{ $candidate['name'] }}</strong>
                                    <div class="assestme-fic-composer__candidate-identifiers">
                                        @if ($candidate['vat_number'])
                                            <span>{{ __('assestme.fatture_in_cloud.composer.vat_number', ['value' => $candidate['vat_number']]) }}</span>
                                        @endif
                                        @if ($candidate['tax_code'])
                                            <span>{{ __('assestme.fatture_in_cloud.composer.tax_code', ['value' => $candidate['tax_code']]) }}</span>
                                        @endif
                                    </div>
                                </div>
                                <x-filament::button wire:click="selectClient('{{ $candidate['id'] }}')" color="gray">
                                    {{ __('assestme.fatture_in_cloud.composer.select_client') }}
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="assestme-fic-composer__message" data-dusk="fic-client-create">
                        <p>{{ __('assestme.fatture_in_cloud.composer.no_exact_client') }}</p>
                        <x-filament::button wire:click="createClient">
                            {{ __('assestme.fatture_in_cloud.composer.create_client') }}
                        </x-filament::button>
                    </div>
                @endif

                @if ($previousDiscoveryFailed)
                    <div class="assestme-fic-composer__previous-notice assestme-fic-composer__previous-notice--warning" data-dusk="fic-previous-error">
                        <p>{{ __('assestme.fatture_in_cloud.composer.previous_discovery_failed') }}</p>
                        <x-filament::button wire:click="discoverPreviousVersion" color="gray" size="sm">
                            {{ __('assestme.fatture_in_cloud.composer.retry') }}
                        </x-filament::button>
                    </div>
                @elseif ($previousDocumentId)
                    <div class="assestme-fic-composer__previous-notice" data-dusk="fic-previous-version">
                        <div>
                            <strong>{{ __('assestme.fatture_in_cloud.composer.previous_heading') }}</strong>
                            <p>{{ __('assestme.fatture_in_cloud.composer.previous_available', ['version' => $previousVersion]) }}</p>
                        </div>
                        <div class="assestme-fic-composer__actions">
                            <x-filament::button wire:click="loadPreviousVersion" size="sm">
                                {{ __('assestme.fatture_in_cloud.composer.load_previous') }}
                            </x-filament::button>
                            <x-filament::button wire:click="startFromZero" color="gray" size="sm">
                                {{ __('assestme.fatture_in_cloud.composer.start_zero') }}
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </x-filament::section>

            @if ($resolvedClientId)
                @if ($vatLoadFailed || $productSearchFailed)
                    <div class="assestme-fic-composer__catalog-warning" data-dusk="fic-catalog-error" role="status">
                        <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                        <p>{{ $vatLoadFailed ? __('assestme.fatture_in_cloud.composer.vat_load_failed') : __('assestme.fatture_in_cloud.composer.product_search_failed') }}</p>
                    </div>
                @endif

                @php($summary = $this->rowSummary())
                <div class="assestme-fic-composer__workspace">
                    <x-filament::section class="assestme-fic-composer__context" data-dusk="fic-findings-panel">
                        <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.findings_heading') }}</x-slot>
                        <x-slot name="afterHeader">
                            <span class="assestme-fic-composer__count">
                                {{ trans_choice('assestme.fatture_in_cloud.composer.findings_count', count($findings), ['count' => count($findings)]) }}
                            </span>
                        </x-slot>

                        <div class="assestme-fic-composer__search">
                            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                                <x-filament::input
                                    id="fic-finding-search"
                                    type="search"
                                    wire:model.live.debounce.250ms="findingSearch"
                                    :placeholder="__('assestme.fatture_in_cloud.composer.finding_search_placeholder')"
                                    :aria-label="__('assestme.fatture_in_cloud.composer.finding_search')"
                                    data-dusk="fic-finding-search"
                                />
                            </x-filament::input.wrapper>
                        </div>

                        <div class="assestme-fic-composer__findings" data-dusk="fic-findings">
                            @forelse ($this->filteredFindings() as $finding)
                                <article class="assestme-fic-composer__finding">
                                    <div class="assestme-fic-composer__finding-header">
                                        <strong>{{ $finding['reference'] }} — {{ $finding['title'] }}</strong>
                                        <span class="assestme-fic-composer__finding-state">
                                            @if ($this->isFindingLinked($finding['id']))
                                                <x-filament::icon icon="heroicon-m-link" />
                                                {{ __('assestme.fatture_in_cloud.composer.finding_linked') }}
                                            @else
                                                <x-filament::icon icon="heroicon-m-minus-circle" />
                                                {{ __('assestme.fatture_in_cloud.composer.finding_not_linked') }}
                                            @endif
                                        </span>
                                    </div>
                                    <div class="assestme-fic-composer__finding-problem">
                                        <span>{{ __('assestme.fatture_in_cloud.composer.finding_problem') }}</span>
                                        <p>{{ $finding['problem'] }}</p>
                                    </div>
                                    <details class="assestme-fic-composer__solution-list">
                                        <summary>
                                            {{ trans_choice('assestme.fatture_in_cloud.composer.finding_solutions', count($finding['solutions']), ['count' => count($finding['solutions'])]) }}
                                        </summary>
                                        <div>
                                            @foreach ($finding['solutions'] as $solution)
                                                <article class="assestme-fic-composer__solution">
                                                    <strong>{{ $solution['title'] }} — {{ $solution['estimate'] }}</strong>
                                                    @if ($solution['description'] !== '')
                                                        <p>{{ $solution['description'] }}</p>
                                                    @endif
                                                </article>
                                            @endforeach
                                        </div>
                                    </details>
                                </article>
                            @empty
                                <p class="assestme-fic-composer__empty">{{ __('assestme.fatture_in_cloud.composer.finding_search_empty') }}</p>
                            @endforelse
                        </div>
                    </x-filament::section>

                    <x-filament::section class="assestme-fic-composer__rows-panel" data-dusk="fic-rows-panel">
                        <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.rows_heading') }}</x-slot>
                        <x-slot name="afterHeader">
                            <div class="assestme-fic-composer__actions">
                                <x-filament::button wire:click="addGroup">{{ __('assestme.fatture_in_cloud.composer.add_group') }}</x-filament::button>
                                <x-filament::button wire:click="addFreeRow" color="gray">{{ __('assestme.fatture_in_cloud.composer.add_free_row') }}</x-filament::button>
                            </div>
                        </x-slot>

                        <div class="assestme-fic-composer__rows" data-dusk="fic-rows">
                            <datalist id="fic-measure-suggestions">
                                @foreach ($this->productMeasures() as $measure)
                                    <option value="{{ $measure }}"></option>
                                @endforeach
                            </datalist>
                            @forelse ($rows as $index => $row)
                                <article wire:key="fic-row-{{ $row['id'] }}" class="assestme-fic-composer__row">
                                    @if ($row['unmatched'])
                                        <p class="assestme-fic-composer__warning">
                                            <x-filament::icon icon="heroicon-m-exclamation-triangle" />
                                            {{ __('assestme.fatture_in_cloud.composer.unmatched_row') }}
                                        </p>
                                    @endif

                                    <div class="assestme-fic-composer__row-header">
                                        <div class="assestme-fic-composer__row-identity">
                                            <span>{{ __('assestme.fatture_in_cloud.composer.row_number', ['number' => $index + 1]) }}</span>
                                            <strong>{{ $row['kind'] === 'group' ? __('assestme.fatture_in_cloud.composer.group') : __('assestme.fatture_in_cloud.composer.free_row') }}</strong>
                                            @if ($row['kind'] === 'group' && count($row['finding_ids']) > 0)
                                                <small>{{ collect($findings)->whereIn('id', $row['finding_ids'])->pluck('reference')->implode(', ') }}</small>
                                            @endif
                                        </div>
                                        <div class="assestme-fic-composer__row-actions">
                                            <x-filament::icon-button icon="heroicon-m-arrow-up" color="gray" size="sm" wire:click="moveRow({{ $index }}, -1)" :label="__('assestme.fatture_in_cloud.composer.move_up')" />
                                            <x-filament::icon-button icon="heroicon-m-arrow-down" color="gray" size="sm" wire:click="moveRow({{ $index }}, 1)" :label="__('assestme.fatture_in_cloud.composer.move_down')" />
                                            <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" wire:click="removeRow({{ $index }})" :label="__('assestme.fatture_in_cloud.composer.remove')" />
                                        </div>
                                    </div>

                                    <label class="assestme-fic-composer__field" for="fic-row-product-{{ $index }}">
                                        <span>{{ __('assestme.fatture_in_cloud.composer.product') }}</span>
                                        <x-filament::input.wrapper>
                                            <x-filament::input.select
                                                id="fic-row-product-{{ $index }}"
                                                data-dusk="fic-row-product-{{ $index }}"
                                                wire:focus="searchProducts"
                                                wire:change="selectProduct({{ $index }}, $event.target.value)"
                                            >
                                                <option value="">{{ __('assestme.fatture_in_cloud.composer.no_product') }}</option>
                                                @foreach ($products as $product)
                                                    <option value="{{ $product['id'] }}" @selected($row['product_id'] === $product['id'])>{{ $product['label'] }}</option>
                                                @endforeach
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                    </label>

                                    <label class="assestme-fic-composer__field" for="fic-row-title-{{ $index }}">
                                        <span>{{ __('assestme.fatture_in_cloud.composer.row_title') }}</span>
                                        <x-filament::input.wrapper>
                                            <x-filament::input id="fic-row-title-{{ $index }}" data-dusk="fic-row-title-{{ $index }}" wire:model="rows.{{ $index }}.title" />
                                        </x-filament::input.wrapper>
                                    </label>

                                    <label class="assestme-fic-composer__field" for="fic-row-description-{{ $index }}">
                                        <span>{{ __('assestme.fatture_in_cloud.composer.row_description') }}</span>
                                        <x-filament::input.wrapper>
                                            <textarea id="fic-row-description-{{ $index }}" class="assestme-fic-composer__textarea" data-dusk="fic-row-description-{{ $index }}" wire:model="rows.{{ $index }}.description" rows="4"></textarea>
                                        </x-filament::input.wrapper>
                                    </label>

                                    <div class="assestme-fic-composer__field-grid assestme-fic-composer__field-grid--commercial" data-dusk="fic-commercial-fields-{{ $index }}">
                                        <label class="assestme-fic-composer__field" for="fic-row-quantity-{{ $index }}">
                                            <span>{{ __('assestme.fatture_in_cloud.composer.quantity') }}</span>
                                            <x-filament::input.wrapper>
                                                <x-filament::input id="fic-row-quantity-{{ $index }}" type="number" min="0.0001" step="0.01" wire:model.live.debounce.250ms="rows.{{ $index }}.quantity" />
                                            </x-filament::input.wrapper>
                                        </label>
                                        <label class="assestme-fic-composer__field" for="fic-row-measure-{{ $index }}">
                                            <span>{{ __('assestme.fatture_in_cloud.composer.measure') }}</span>
                                            <x-filament::input.wrapper suffix="U.M.">
                                                <x-filament::input id="fic-row-measure-{{ $index }}" data-dusk="fic-row-measure-{{ $index }}" list="fic-measure-suggestions" wire:focus="searchProducts" wire:model="rows.{{ $index }}.measure" />
                                            </x-filament::input.wrapper>
                                        </label>
                                        <label class="assestme-fic-composer__field" for="fic-row-price-{{ $index }}">
                                            <span>{{ __('assestme.fatture_in_cloud.composer.net_price') }}</span>
                                            <x-filament::input.wrapper suffix="€">
                                                <x-filament::input id="fic-row-price-{{ $index }}" data-dusk="fic-row-price-{{ $index }}" type="number" min="0" step="0.01" wire:model.live.debounce.250ms="rows.{{ $index }}.net_price" />
                                            </x-filament::input.wrapper>
                                        </label>
                                        <label class="assestme-fic-composer__field" for="fic-row-discount-{{ $index }}">
                                            <span>{{ __('assestme.fatture_in_cloud.composer.discount') }}</span>
                                            <x-filament::input.wrapper suffix="%">
                                                <x-filament::input id="fic-row-discount-{{ $index }}" type="number" min="0" max="100" step="0.01" wire:model.live.debounce.250ms="rows.{{ $index }}.discount" />
                                            </x-filament::input.wrapper>
                                        </label>
                                        <label class="assestme-fic-composer__field" for="fic-row-vat-{{ $index }}">
                                            <span>{{ __('assestme.fatture_in_cloud.composer.vat') }}</span>
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select id="fic-row-vat-{{ $index }}" wire:model="rows.{{ $index }}.vat_type_id">
                                                    @foreach ($vatTypes as $vat)
                                                        <option value="{{ $vat['id'] }}">{{ $vat['label'] }}</option>
                                                    @endforeach
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        </label>
                                    </div>

                                    @if ($row['kind'] === 'group')
                                        <details class="assestme-fic-composer__assignments" open>
                                            <summary>{{ __('assestme.fatture_in_cloud.composer.assign_findings') }}</summary>
                                            <div class="assestme-fic-composer__assignment-content">
                                                @foreach ($findings as $finding)
                                                    <label class="assestme-fic-composer__choice">
                                                        <x-filament::input.checkbox
                                                            data-dusk="fic-row-finding-{{ $index }}-{{ $finding['id'] }}"
                                                            value="{{ $finding['id'] }}"
                                                            :checked="$this->rowIncludesFinding($index, $finding['id'])"
                                                            wire:change="updateRowFinding({{ $index }}, {{ $finding['id'] }}, $event.target.checked)"
                                                        />
                                                        <span>{{ $finding['reference'] }} — {{ $finding['title'] }}</span>
                                                    </label>
                                                    @if ($this->rowIncludesFinding($index, $finding['id']))
                                                        <div class="assestme-fic-composer__solutions">
                                                            <strong>{{ __('assestme.fatture_in_cloud.composer.reference_estimates') }}</strong>
                                                            @foreach ($finding['solutions'] as $solution)
                                                                <label class="assestme-fic-composer__choice assestme-fic-composer__choice--solution">
                                                                    <x-filament::input.checkbox value="{{ $solution['id'] }}" wire:model.live="rows.{{ $index }}.solution_ids" />
                                                                    <span>{{ $solution['title'] }} — {{ $solution['estimate'] }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @endforeach
                                                @if ($this->rowHasPriceSuggestion($index))
                                                    <x-filament::button type="button" size="sm" color="gray" wire:click="suggestRowPrice({{ $index }})">
                                                        {{ __('assestme.fatture_in_cloud.composer.suggest_price') }}
                                                    </x-filament::button>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </article>
                            @empty
                                <p class="assestme-fic-composer__empty">{{ __('assestme.fatture_in_cloud.composer.no_rows') }}</p>
                            @endforelse
                        </div>
                    </x-filament::section>

                    <x-filament::section class="assestme-fic-composer__summary" data-dusk="fic-summary-panel">
                        <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.summary_heading') }}</x-slot>

                        <dl class="assestme-fic-composer__summary-list">
                            <div>
                                <dt>{{ __('assestme.fatture_in_cloud.composer.summary_total_rows') }}</dt>
                                <dd data-dusk="fic-summary-total">{{ $summary['total'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('assestme.fatture_in_cloud.composer.summary_finding_rows') }}</dt>
                                <dd data-dusk="fic-summary-finding-rows">{{ $summary['finding_rows'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('assestme.fatture_in_cloud.composer.summary_free_rows') }}</dt>
                                <dd data-dusk="fic-summary-free-rows">{{ $summary['free_rows'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('assestme.fatture_in_cloud.composer.summary_linked_findings') }}</dt>
                                <dd data-dusk="fic-summary-linked-findings">{{ $summary['linked_findings'] }} / {{ $summary['total_findings'] }}</dd>
                            </div>
                            <div class="assestme-fic-composer__summary-total">
                                <dt>
                                    {{ __('assestme.fatture_in_cloud.composer.summary_net_total') }}
                                    <small>{{ __('assestme.fatture_in_cloud.composer.summary_vat_excluded') }}</small>
                                </dt>
                                <dd data-dusk="fic-summary-net-total">{{ $this->formattedNetTotal() }}</dd>
                            </div>
                        </dl>

                        <div class="assestme-fic-composer__submit" data-dusk="fic-submit-panel">
                            @if ($submissionMessage)
                                <p class="assestme-fic-composer__submission-status" data-dusk="fic-submission-status" aria-live="polite">
                                    {{ $submissionMessage }}
                                </p>
                            @endif
                            @if ($submissionStatus === 'success')
                                <div class="assestme-fic-composer__success">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.created_id', ['id' => $submittedDocumentId]) }}</span>
                                    @if ($submittedDocumentUrl)
                                        <x-filament::button tag="a" href="{{ $submittedDocumentUrl }}" target="_blank" rel="noopener noreferrer">
                                            {{ __('assestme.fatture_in_cloud.composer.open_created') }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            @else
                                <x-filament::button
                                    class="assestme-fic-composer__submit-button"
                                    wire:click="submitQuote"
                                    wire:loading.attr="disabled"
                                    wire:target="submitQuote"
                                    :disabled="$submissionInProgress || count($rows) === 0"
                                    data-dusk="fic-submit"
                                >
                                    {{ $submissionInProgress ? __('assestme.fatture_in_cloud.composer.submitting') : __('assestme.fatture_in_cloud.composer.submit') }}
                                </x-filament::button>
                            @endif
                        </div>
                    </x-filament::section>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
