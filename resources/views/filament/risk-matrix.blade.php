<?php declare(strict_types=1); ?>

@if (count($consequences) === 4 && count($likelihoods) === 4 && count($priorities) > 0)
    <div class="overflow-x-auto" data-dusk="risk-matrix-grid">
        <table class="w-full border-collapse text-sm" style="min-width: 52rem; table-layout: fixed">
            <colgroup>
                <col style="width: 20%">
                <col style="width: 20%">
                <col style="width: 20%">
                <col style="width: 20%">
                <col style="width: 20%">
            </colgroup>
            <thead>
                <tr>
                    <th class="border border-gray-200 p-3 text-left dark:border-white/10">
                        {{ __('assestme.risk.fields.consequence') }} / {{ __('assestme.risk.fields.likelihood') }}
                    </th>
                    @foreach ($likelihoods as $likelihood)
                        <th class="border border-gray-200 p-3 text-left dark:border-white/10">
                            {{ $likelihood['label'] ?? '' }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($consequences as $consequenceIndex => $consequence)
                    <tr>
                        <th class="border border-gray-200 p-3 text-left dark:border-white/10">
                            {{ $consequence['label'] ?? '' }}
                        </th>
                        @foreach ($likelihoods as $likelihoodIndex => $likelihood)
                            @php($matrixIndex = ($consequenceIndex * 4) + $likelihoodIndex)
                            @php($selectedCode = $getState()[$matrixIndex]['priority_code'] ?? null)
                            @php($selectedPriority = collect($priorities)->firstWhere('code', $selectedCode))
                            <td class="border border-gray-200 p-2 align-top dark:border-white/10">
                                <select
                                    wire:model.live="{{ $getStatePath() }}.{{ $matrixIndex }}.priority_code"
                                    class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-gray-900"
                                    style="width: 100%"
                                    aria-label="{{ ($consequence['label'] ?? '').' / '.($likelihood['label'] ?? '') }}"
                                >
                                    @foreach ($priorities as $priority)
                                        <option value="{{ $priority['code'] ?? '' }}">{{ $priority['label'] ?? '' }}</option>
                                    @endforeach
                                </select>
                                @if (is_array($selectedPriority))
                                    <div class="mt-2 flex items-center gap-2 text-xs">
                                        <span
                                            aria-hidden="true"
                                            style="display: inline-block; width: 0.75rem; height: 0.75rem; min-width: 0.75rem; border-radius: 9999px; background-color: {{ $selectedPriority['color'] ?? '#6B7280' }}"
                                        ></span>
                                        <span>{{ $selectedPriority['label'] ?? '' }}</span>
                                    </div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-sm text-gray-500">{{ __('assestme.risk.matrix_incomplete') }}</p>
@endif
