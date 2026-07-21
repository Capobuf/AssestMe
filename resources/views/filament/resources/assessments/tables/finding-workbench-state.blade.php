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
        <span class="assestme-finding-row__metadata">
            {{ \App\Filament\Resources\Assessments\Tables\AssessmentFindingsTable::metadata($record) }}
        </span>
        <div class="assestme-finding-row__signals">
            <span
                class="assestme-finding-row__priority"
                style="--assestme-priority-color: {{ $record->priorityLevel?->color ?? '#9CA3AF' }}"
            >
                <span aria-hidden="true"></span>
                {{ $record->priorityLevel?->label ?? __('assestme.workspace.list.priority_missing') }}
            </span>
            <span class="assestme-finding-row__state">
                {{ \App\Enums\FindingStatus::options()[$record->status->value] }}
            </span>
            @if (! $record->include_in_report)
                <span class="assestme-finding-row__completion is-muted">
                    {{ __('assestme.workspace.list.excluded') }}
                </span>
            @elseif ($messages !== [])
                <span class="assestme-finding-row__completion is-incomplete" title="{{ implode(' · ', $messages) }}">
                    {{ __('assestme.workspace.list.incomplete') }}
                </span>
            @else
                <span class="assestme-finding-row__completion is-complete">
                    {{ __('assestme.workspace.list.complete') }}
                </span>
            @endif
        </div>
    </div>
</div>
