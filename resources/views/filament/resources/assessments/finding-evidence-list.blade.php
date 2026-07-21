<div class="assestme-finding-evidence-list">
    @forelse ($evidences as $evidence)
        <div class="assestme-finding-evidence-list__item">
            <div>
                @if ($evidence->type === \App\Enums\EvidenceType::File)
                    <a href="{{ route('evidence.download', $evidence) }}" target="_blank" rel="noopener">
                        <strong>{{ $evidence->title }}</strong>
                    </a>
                @else
                    <a href="{{ $evidence->url }}" target="_blank" rel="noopener noreferrer">
                        <strong>{{ $evidence->title }}</strong>
                    </a>
                @endif
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
