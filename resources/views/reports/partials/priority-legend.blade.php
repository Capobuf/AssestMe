<?php declare(strict_types=1); ?>

@if ($report->setting('show_priority_descriptions') === true)
    <section class="priority-legend-section avoid-break">
        <h2 class="priority-legend-heading">{{ __('assestme.reports.document.priority_legend') }}</h2>
        <table class="priority-legend priority-legend--detailed">
            @foreach ($report->priorityLegend as $priority)
                <tr>
                    <td class="priority-legend__marker">
                        <span class="priority-glyph" style="color: {{ $priority->color }}">●</span>
                    </td>
                    <td class="priority-legend__label">{{ $priority->label }}</td>
                    <td>
                        @if (filled($priority->description))
                            {{ $priority->description }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    </section>
@else
    <table class="priority-legend-inline avoid-break">
        <tr>
            <th>{{ __('assestme.reports.document.priority_legend') }}</th>
            @foreach ($report->priorityLegend as $priority)
                <td>
                    <span class="priority-glyph" style="color: {{ $priority->color }}">●</span>
                    {{ $priority->label }}
                </td>
            @endforeach
        </tr>
    </table>
@endif
