<?php declare(strict_types=1); ?>
@php
    $statusGlyph = static fn (string $status): string => match ($status) {
        'open' => '○',
        'planned' => '▦',
        'in_progress' => '⌛',
        'resolved' => '✓',
        'accepted' => '●',
        'not_applicable' => '●',
        default => '○',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $report->locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    @include('reports.partials.report-styles')
</head>
<body @if ($isSettingsPreview ?? false) class="assestme-report-preview-document" @endif>
    @if ($report->setting('cover') === true)
        @include('reports.partials.cover')
    @endif

    @if ($report->setting('executive_summary') === true)
        @include('reports.partials.overview')
    @endif

    @if ($report->setting('content_index') === true)
        @include('reports.partials.content-index')
    @endif

    @if ($report->setting('summary_table') === true)
        @include('reports.partials.findings-summary')
    @endif

    @foreach ($report->findings as $finding)
        @include('reports.partials.finding-detail', ['finding' => $finding])

        @if ($report->setting('evidence') === true && $finding->includedEvidence() !== [])
            @include('reports.partials.finding-evidence', ['finding' => $finding])
        @endif
    @endforeach

    @include('reports.partials.final-sections')

    @if ($isSettingsPreview ?? false)
        <script>
            (() => {
                const body = document.body;
                const sheets = Array.from(body.querySelectorAll(':scope > section, :scope > article'));

                if (sheets.length === 0) {
                    return;
                }

                for (const sheet of sheets) {
                    const slot = document.createElement('div');
                    slot.className = 'assestme-report-preview-sheet-slot';
                    sheet.before(slot);
                    sheet.classList.add('assestme-report-preview-sheet');
                    slot.append(sheet);
                }

                const fitSheets = () => {
                    const availableWidth = document.documentElement.clientWidth;

                    for (const slot of body.querySelectorAll('.assestme-report-preview-sheet-slot')) {
                        const sheet = slot.firstElementChild;
                        sheet.style.transform = 'none';

                        const scale = availableWidth / sheet.offsetWidth;
                        slot.style.height = `${sheet.offsetHeight * scale}px`;
                        sheet.style.transform = `scale(${scale})`;
                    }

                    body.dataset.reportPreviewFitted = 'true';
                    body.dataset.reportPreviewPageCount = String(sheets.length);
                };

                requestAnimationFrame(() => {
                    fitSheets();
                    requestAnimationFrame(fitSheets);
                });
                window.addEventListener('resize', fitSheets);
            })();
        </script>
    @endif
</body>
</html>
