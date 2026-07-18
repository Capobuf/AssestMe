<?php declare(strict_types=1); ?>

@php
    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $state = is_array($getRawState()) ? $getRawState() : [];
    $consequences = $getConsequences();
    $likelihoods = $getLikelihoods();
    $priorities = $getPriorities();
    $prioritiesByIdentity = $getPrioritiesByIdentity();
    $isDisabled = $isDisabled();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="assestme-risk-matrix-field"
>
    @if ($errors->has('matrix'))
        <p class="fi-fo-field-wrp-error-message assestme-risk-matrix__error" data-validation-error>
            {{ $errors->first('matrix') }}
        </p>
    @endif

    @if (count($consequences) === 4 && count($likelihoods) === 4 && count($priorities) > 0)
        <div class="assestme-risk-matrix" data-dusk="risk-matrix-grid">
            <table class="assestme-risk-matrix__table">
                <thead>
                    <tr>
                        <th class="assestme-risk-matrix__header assestme-risk-matrix__corner" scope="col">
                            {{ __('assestme.risk.matrix_axes') }}
                        </th>
                        @foreach ($likelihoods as $likelihood)
                            <th class="assestme-risk-matrix__header" scope="col">
                                {{ $likelihood['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($consequences as $consequence)
                        <tr>
                            <th class="assestme-risk-matrix__header assestme-risk-matrix__row-header" scope="row">
                                {{ $consequence['label'] }}
                            </th>
                            @foreach ($likelihoods as $likelihood)
                                @php
                                    $cellPath = "{$statePath}.{$consequence['identity']}.{$likelihood['identity']}";
                                    $relativeCellPath = "matrix.{$consequence['identity']}.{$likelihood['identity']}";
                                    $cellId = 'risk-matrix-'.md5($cellPath);
                                    $selectedIdentity = (string) ($state[$consequence['identity']][$likelihood['identity']] ?? '');
                                    $selectedPriority = $prioritiesByIdentity[$selectedIdentity] ?? null;
                                    $cellErrorPath = $errors->has($cellPath) ? $cellPath : $relativeCellPath;
                                    $cellHasError = $errors->has($cellErrorPath);
                                @endphp
                                <td
                                    @class([
                                        'assestme-risk-matrix__cell',
                                        'assestme-risk-matrix__cell--empty' => $selectedPriority === null,
                                        'assestme-risk-matrix__cell--invalid' => $cellHasError,
                                    ])
                                    @if ($selectedPriority !== null)
                                        style="--assestme-risk-color: {{ $selectedPriority['color'] }}"
                                    @endif
                                >
                                    <label class="fi-sr-only" for="{{ $cellId }}">
                                        {{ __('assestme.risk.matrix_cell_label', [
                                            'consequence' => $consequence['label'],
                                            'likelihood' => $likelihood['label'],
                                        ]) }}
                                    </label>

                                    <x-filament::input.wrapper
                                        :disabled="$isDisabled"
                                        :valid="! $cellHasError"
                                    >
                                        <x-filament::input.select
                                            id="{{ $cellId }}"
                                            data-dusk="risk-matrix-cell-{{ $consequence['identity'] }}-{{ $likelihood['identity'] }}"
                                            :disabled="$isDisabled"
                                            wire:model.live="{{ $cellPath }}"
                                        >
                                            <option value="">{{ __('assestme.risk.matrix_empty') }}</option>
                                            @foreach ($priorities as $priority)
                                                <option value="{{ $priority['identity'] }}">
                                                    {{ $priority['label'] }}@if (! $priority['is_enabled']) {{ __('assestme.risk.disabled_suffix') }}@endif
                                                </option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>

                                    <div class="assestme-risk-matrix__priority" aria-live="polite">
                                        @if ($selectedPriority !== null)
                                            <span
                                                class="assestme-risk-matrix__color"
                                                style="background-color: {{ $selectedPriority['color'] }}"
                                                aria-hidden="true"
                                            ></span>
                                            <span>{{ $selectedPriority['label'] }}</span>
                                        @else
                                            <span class="assestme-risk-matrix__empty-indicator" aria-hidden="true">—</span>
                                            <span>{{ __('assestme.risk.matrix_empty') }}</span>
                                        @endif
                                    </div>

                                    @error($cellErrorPath)
                                        <p class="fi-fo-field-wrp-error-message" data-validation-error>
                                            {{ $message }}
                                        </p>
                                    @enderror
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="assestme-risk-matrix__incomplete" data-dusk="risk-matrix-incomplete">
            {{ __('assestme.risk.matrix_incomplete') }}
        </p>
    @endif
</x-dynamic-component>
