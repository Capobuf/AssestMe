@php($record = $getRecord())
<div
    class="assestme-finding-priority"
    style="--assestme-priority-color: {{ $record->priorityLevel?->color ?? '#8A8A8A' }}"
>
    <span class="assestme-finding-priority__indicator" aria-hidden="true"></span>
    <span>{{ $record->priorityLevel?->label ?? __('assestme.workspace.list.priority_missing') }}</span>
</div>
