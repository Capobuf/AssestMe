@php
    $mode = $preview['mode'] ?? 'new';
    $candidates = $preview['candidates'] ?? [];
    $diff = $preview['diff'] ?? ['fields' => [], 'solutions' => ['added' => 0, 'changed' => 0, 'removed' => 0]];
@endphp

<div class="space-y-4 text-sm text-gray-700 dark:text-gray-200">
    @if ($mode === 'exact')
        <p>{{ __('assestme.template_learning.descriptions.exact') }}</p>
    @elseif ($mode === 'similar')
        <p>{{ __('assestme.template_learning.descriptions.similar') }}</p>
    @elseif ($mode === 'update')
        <p>{{ __('assestme.template_learning.descriptions.update') }}</p>
    @else
        <p>{{ __('assestme.template_learning.descriptions.save') }}</p>
    @endif

    @if ($preview['priority_override'] ?? false)
        <p class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-600 dark:bg-amber-950 dark:text-amber-100">
            {{ __('assestme.template_learning.descriptions.priority_override') }}
        </p>
    @endif

    @if (in_array($mode, ['exact', 'similar', 'update'], true))
        <div class="space-y-2">
            @foreach ($candidates as $candidate)
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                    <div class="font-medium text-gray-950 dark:text-white">{{ $candidate['title'] }}</div>
                    <div>{{ __('assestme.template_learning.candidate.category', ['category' => $candidate['category']]) }}</div>
                    @if ($candidate['disabled'])
                        <div class="mt-1 font-medium">{{ __('assestme.template_learning.candidate.disabled') }}</div>
                    @endif
                    <a class="fi-link mt-2 inline-flex" href="{{ $candidate['url'] }}" target="_blank" rel="noopener noreferrer">
                        {{ __('assestme.template_learning.actions.open_template') }}
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    @if ($mode === 'update')
        <ul class="space-y-1">
            @foreach ($diff['fields'] as $field => $changed)
                <li>
                    {{ __('assestme.template_learning.diff.'.$field) }} —
                    {{ __('assestme.template_learning.diff.'.($changed ? 'changed' : 'unchanged')) }}
                </li>
            @endforeach
            <li>{{ __('assestme.template_learning.diff.solutions', $diff['solutions']) }}</li>
        </ul>
        <p class="font-medium text-gray-950 dark:text-white">
            {{ __('assestme.template_learning.descriptions.existing_findings_unchanged') }}
        </p>
    @endif
</div>
