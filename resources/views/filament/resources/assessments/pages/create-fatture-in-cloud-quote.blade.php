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
    <x-filament::section>
        <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.client_heading') }}</x-slot>

        @if ($clientResolutionFailed)
            <div class="assestme-fic-composer__message" data-dusk="fic-client-error">
                <p>{{ __('assestme.fatture_in_cloud.composer.client_resolution_failed') }}</p>
                <x-filament::button wire:click="resolveClient" color="gray">
                    {{ __('assestme.fatture_in_cloud.composer.retry') }}
                </x-filament::button>
            </div>
        @elseif ($resolvedClientId)
            <p data-dusk="fic-client-resolved">
                {{ __('assestme.fatture_in_cloud.composer.client_resolved', ['name' => $resolvedClientName]) }}
            </p>
        @elseif (count($clientCandidates) > 0)
            <div class="assestme-fic-composer__message" data-dusk="fic-client-candidates">
                <p>{{ __('assestme.fatture_in_cloud.composer.choose_exact_client') }}</p>
                @foreach ($clientCandidates as $candidate)
                    <div class="assestme-fic-composer__candidate">
                        <div>
                            <strong>{{ $candidate['name'] }}</strong>
                            <div class="assestme-fic-composer__muted">
                                {{ $candidate['vat_number'] ?: $candidate['tax_code'] }}
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
    </x-filament::section>

    @if ($previousDiscoveryFailed)
        <x-filament::section data-dusk="fic-previous-error">
            <p>{{ __('assestme.fatture_in_cloud.composer.previous_discovery_failed') }}</p>
            <x-filament::button wire:click="discoverPreviousVersion" color="gray">{{ __('assestme.fatture_in_cloud.composer.retry') }}</x-filament::button>
        </x-filament::section>
    @elseif ($previousDocumentId)
        <x-filament::section data-dusk="fic-previous-version">
            <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.previous_heading') }}</x-slot>
            <p>{{ __('assestme.fatture_in_cloud.composer.previous_available', ['version' => $previousVersion]) }}</p>
            <div class="assestme-fic-composer__actions">
                <x-filament::button wire:click="loadPreviousVersion">{{ __('assestme.fatture_in_cloud.composer.load_previous') }}</x-filament::button>
                <x-filament::button wire:click="startFromZero" color="gray">{{ __('assestme.fatture_in_cloud.composer.start_zero') }}</x-filament::button>
            </div>
        </x-filament::section>
    @endif

    @if ($resolvedClientId)
        @if ($vatLoadFailed || $productSearchFailed)
            <x-filament::section data-dusk="fic-catalog-error">
                <p>{{ $vatLoadFailed ? __('assestme.fatture_in_cloud.composer.vat_load_failed') : __('assestme.fatture_in_cloud.composer.product_search_failed') }}</p>
            </x-filament::section>
        @endif
        <div class="assestme-fic-composer__workspace">
            <x-filament::section class="assestme-fic-composer__context">
                <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.findings_heading') }}</x-slot>
                <div class="assestme-fic-composer__findings" data-dusk="fic-findings">
                    @foreach ($findings as $finding)
                        <article class="assestme-fic-composer__finding">
                            <strong>{{ $finding['reference'] }} — {{ $finding['title'] }}</strong>
                            <p class="assestme-fic-composer__finding-problem">{{ $finding['problem'] }}</p>
                            @foreach ($finding['solutions'] as $solution)
                                <details class="assestme-fic-composer__solution">
                                    <summary>{{ $solution['title'] }} — {{ $solution['estimate'] }}</summary>
                                    <p>{{ $solution['description'] }}</p>
                                </details>
                            @endforeach
                        </article>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section class="assestme-fic-composer__rows-panel">
                <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.rows_heading') }}</x-slot>
                <x-slot name="afterHeader">
                    <div class="assestme-fic-composer__actions">
                        <x-filament::button wire:click="addGroup">{{ __('assestme.fatture_in_cloud.composer.add_group') }}</x-filament::button>
                        <x-filament::button wire:click="addFreeRow" color="gray">{{ __('assestme.fatture_in_cloud.composer.add_free_row') }}</x-filament::button>
                    </div>
                </x-slot>
                <div class="assestme-fic-composer__rows" data-dusk="fic-rows">
                    @forelse ($rows as $index => $row)
                        <article wire:key="fic-row-{{ $row['id'] }}" class="assestme-fic-composer__row">
                            @if ($row['unmatched'])
                                <p class="assestme-fic-composer__warning">{{ __('assestme.fatture_in_cloud.composer.unmatched_row') }}</p>
                            @endif
                            <div class="assestme-fic-composer__row-header">
                                <strong>{{ $row['kind'] === 'group' ? __('assestme.fatture_in_cloud.composer.group') : __('assestme.fatture_in_cloud.composer.free_row') }}</strong>
                                <div class="assestme-fic-composer__row-actions">
                                    <x-filament::icon-button icon="heroicon-m-arrow-up" color="gray" size="sm" wire:click="moveRow({{ $index }}, -1)" :label="__('assestme.fatture_in_cloud.composer.move_up')" />
                                    <x-filament::icon-button icon="heroicon-m-arrow-down" color="gray" size="sm" wire:click="moveRow({{ $index }}, 1)" :label="__('assestme.fatture_in_cloud.composer.move_down')" />
                                    <x-filament::icon-button icon="heroicon-m-trash" color="danger" size="sm" wire:click="removeRow({{ $index }})" :label="__('assestme.fatture_in_cloud.composer.remove')" />
                                </div>
                            </div>
                            <div class="assestme-fic-composer__field-grid assestme-fic-composer__field-grid--single">
                                <label class="assestme-fic-composer__field" for="fic-row-title-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.row_title') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="fic-row-title-{{ $index }}" data-dusk="fic-row-title-{{ $index }}" wire:model="rows.{{ $index }}.title" />
                                    </x-filament::input.wrapper>
                                </label>
                                <label class="assestme-fic-composer__field" for="fic-row-description-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.row_description') }}</span>
                                    <x-filament::input.wrapper>
                                        <textarea id="fic-row-description-{{ $index }}" class="assestme-fic-composer__textarea" data-dusk="fic-row-description-{{ $index }}" wire:model="rows.{{ $index }}.description" rows="3"></textarea>
                                    </x-filament::input.wrapper>
                                </label>
                            </div>
                            <div class="assestme-fic-composer__field-grid assestme-fic-composer__field-grid--commercial">
                                <label class="assestme-fic-composer__field" for="fic-row-price-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.net_price') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="fic-row-price-{{ $index }}" data-dusk="fic-row-price-{{ $index }}" type="number" min="0" step="0.01" wire:model="rows.{{ $index }}.net_price" />
                                    </x-filament::input.wrapper>
                                </label>
                                <label class="assestme-fic-composer__field" for="fic-row-quantity-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.quantity') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="fic-row-quantity-{{ $index }}" type="number" min="0.0001" step="0.01" wire:model="rows.{{ $index }}.quantity" />
                                    </x-filament::input.wrapper>
                                </label>
                                <label class="assestme-fic-composer__field" for="fic-row-measure-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.measure') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input id="fic-row-measure-{{ $index }}" wire:model="rows.{{ $index }}.measure" />
                                    </x-filament::input.wrapper>
                                </label>
                                <label class="assestme-fic-composer__field" for="fic-row-discount-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.discount') }}</span>
                                    <x-filament::input.wrapper suffix="%">
                                        <x-filament::input id="fic-row-discount-{{ $index }}" type="number" min="0" max="100" step="0.01" wire:model="rows.{{ $index }}.discount" />
                                    </x-filament::input.wrapper>
                                </label>
                            </div>
                            <div class="assestme-fic-composer__field-grid assestme-fic-composer__field-grid--catalog">
                                <label class="assestme-fic-composer__field" for="fic-row-product-{{ $index }}">
                                    <span>{{ __('assestme.fatture_in_cloud.composer.product') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select id="fic-row-product-{{ $index }}" wire:focus="searchProducts" wire:change="selectProduct({{ $index }}, $event.target.value)">
                                            <option value="">{{ __('assestme.fatture_in_cloud.composer.no_product') }}</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product['id'] }}" @selected($row['product_id'] === $product['id'])>{{ $product['label'] }}</option>
                                            @endforeach
                                        </x-filament::input.select>
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
                                <fieldset class="assestme-fic-composer__assignments">
                                    <legend>{{ __('assestme.fatture_in_cloud.composer.assign_findings') }}</legend>
                                    @foreach ($findings as $finding)
                                        <label class="assestme-fic-composer__choice"><x-filament::input.checkbox data-dusk="fic-row-finding-{{ $index }}-{{ $finding['id'] }}" value="{{ $finding['id'] }}" wire:model.live="rows.{{ $index }}.finding_ids" /> <span>{{ $finding['reference'] }} — {{ $finding['title'] }}</span></label>
                                        @if (in_array($finding['id'], $row['finding_ids'], true))
                                            <div class="assestme-fic-composer__solutions">
                                                @foreach ($finding['solutions'] as $solution)
                                                    <label class="assestme-fic-composer__choice assestme-fic-composer__choice--solution"><x-filament::input.checkbox value="{{ $solution['id'] }}" wire:model="rows.{{ $index }}.solution_ids" /> <span>{{ $solution['title'] }} — {{ $solution['estimate'] }}</span></label>
                                                @endforeach
                                            </div>
                                        @endif
                                    @endforeach
                                    <x-filament::button type="button" size="sm" color="gray" wire:click="suggestRowPrice({{ $index }})">
                                        {{ __('assestme.fatture_in_cloud.composer.suggest_price') }}
                                    </x-filament::button>
                                </fieldset>
                            @endif
                        </article>
                    @empty
                        <p>{{ __('assestme.fatture_in_cloud.composer.no_rows') }}</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        <x-filament::section data-dusk="fic-submit-panel">
            <x-slot name="heading">{{ __('assestme.fatture_in_cloud.composer.submit_heading') }}</x-slot>
            @if ($submissionMessage)
                <p data-dusk="fic-submission-status">{{ $submissionMessage }}</p>
            @endif
            @if ($submissionStatus === 'success')
                <div class="assestme-fic-composer__actions">
                    @if ($submittedDocumentUrl)
                        <x-filament::button tag="a" href="{{ $submittedDocumentUrl }}" target="_blank" rel="noopener noreferrer">
                            {{ __('assestme.fatture_in_cloud.composer.open_created') }}
                        </x-filament::button>
                    @endif
                    <span>{{ __('assestme.fatture_in_cloud.composer.created_id', ['id' => $submittedDocumentId]) }}</span>
                </div>
            @else
                <x-filament::button
                    class="mt-3"
                    wire:click="submitQuote"
                    wire:loading.attr="disabled"
                    wire:target="submitQuote"
                    :disabled="$submissionInProgress || count($rows) === 0"
                    data-dusk="fic-submit"
                >
                    {{ $submissionInProgress ? __('assestme.fatture_in_cloud.composer.submitting') : __('assestme.fatture_in_cloud.composer.submit') }}
                </x-filament::button>
            @endif
        </x-filament::section>
    @endif
    @endif
    </div>
</x-filament-panels::page>
