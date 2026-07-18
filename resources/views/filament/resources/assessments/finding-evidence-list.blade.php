<div class="assestme-finding-evidence-list">
    @forelse ($evidences as $evidence)
        <div class="assestme-finding-evidence-list__item">
            <div>
                <strong>{{ $evidence->title }}</strong>
                <span>{{ $evidence->type->value }}</span>
            </div>
            <span>
                {{ $evidence->include_in_report ? __('assestme.workspace.list.included') : __('assestme.workspace.list.excluded') }}
            </span>
        </div>
    @empty
        <p>{{ __('assestme.workspace.no_evidence') }}</p>
    @endforelse
</div>
