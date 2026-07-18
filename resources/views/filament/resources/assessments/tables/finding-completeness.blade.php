@php
    $record = $getRecord();
    $messages = app(\App\Actions\Assessments\AssessFindingCompleteness::class)($record);
@endphp

<div class="assestme-finding-completeness">
    @if (! $record->include_in_report)
        <span class="assestme-finding-indicator assestme-finding-indicator--muted">
            {{ __('assestme.workspace.list.excluded') }}
        </span>
    @elseif ($messages !== [])
        <span class="assestme-finding-indicator" title="{{ implode(' · ', $messages) }}">
            {{ __('assestme.workspace.list.incomplete') }}
        </span>
    @else
        <span class="assestme-finding-indicator assestme-finding-indicator--complete">
            {{ __('assestme.workspace.list.complete') }}
        </span>
    @endif
</div>
