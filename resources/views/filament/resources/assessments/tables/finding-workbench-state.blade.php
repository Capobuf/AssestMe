@php
    $record = $getRecord();
    $messages = app(\App\Actions\Assessments\AssessFindingCompleteness::class)($record);
@endphp

<div class="assestme-finding-row__layout">
    <span class="assestme-finding-row__number">
        {{ str_pad((string) $record->sort_order, 2, '0', STR_PAD_LEFT) }}
    </span>
    <div class="assestme-finding-row__summary">
        <strong class="assestme-finding-row__title">
            {{ $record->title ?: __('assestme.workspace.list.untitled') }}
        </strong>
        <div class="assestme-finding-row__signals">
            <span
                class="assestme-finding-row__priority"
                style="--assestme-priority-color: {{ $record->priorityLevel?->color ?? '#8A8A8A' }}"
            >
                <span aria-hidden="true"></span>
                {{ $record->priorityLevel?->label ?? __('assestme.workspace.list.priority_missing') }}
            </span>
            @if ($record->status !== \App\Enums\FindingStatus::Open)
                <span class="assestme-finding-row__state">
                    {{ \App\Enums\FindingStatus::options()[$record->status->value] }}
                </span>
            @endif
            @if ($messages !== [])
                <span class="assestme-finding-row__completion is-incomplete" title="{{ implode(' · ', $messages) }}">
                    {{ trans_choice('assestme.workspace.list.incomplete_details', count($messages), ['count' => count($messages)]) }}
                </span>
            @endif
            @if (! $record->include_in_report)
                <span
                    class="assestme-finding-row__report-exclusion"
                    title="{{ __('assestme.workspace.list.excluded') }}"
                    aria-label="{{ __('assestme.workspace.list.excluded') }}"
                >
                    <x-filament::icon icon="heroicon-m-eye-slash" aria-hidden="true" />
                    <span class="assestme-sr-only">{{ __('assestme.workspace.list.excluded') }}</span>
                </span>
            @endif
        </div>
    </div>
</div>
