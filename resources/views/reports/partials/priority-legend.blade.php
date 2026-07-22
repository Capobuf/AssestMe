<?php declare(strict_types=1); ?>

<section class="avoid-break">
    <h2 class="editorial-heading">{{ __('assestme.reports.document.priority_legend') }}</h2>
    <table class="priority-legend">
        @foreach ($report->priorityLegend as $priority)
            <tr>
                <td class="priority-legend__marker">
                    <span class="priority-legend__bar" style="background-color: {{ $priority->color }}"></span>
                </td>
                <td class="priority-legend__label">{{ $priority->label }}</td>
                @if ($report->setting('show_priority_descriptions') === true && filled($priority->description))
                    <td>{{ $priority->description }}</td>
                @endif
            </tr>
        @endforeach
    </table>
</section>
