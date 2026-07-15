<?php declare(strict_types=1); ?>

@if ($reports->isEmpty())
    <p>{{ __('assestme.workspace.no_generated_files') }}</p>
@else
    <div class="space-y-3">
        @foreach ($reports as $generatedReport)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div>
                    <div class="font-medium">{{ strtoupper($generatedReport->format->value) }} · v{{ str_pad((string) $generatedReport->version, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="text-sm text-gray-500">{{ $generatedReport->file_name }} · {{ $generatedReport->generated_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('generated-reports.download', $generatedReport) }}"
                        data-dusk="download-generated-report"
                        class="fi-btn fi-btn-size-md fi-color fi-color-primary"
                    >
                        {{ __('assestme.reports.download_existing') }}
                    </a>
                    {{ ($this->deleteGeneratedReportAction)(['report' => $generatedReport->getKey()]) }}
                </div>
            </div>
        @endforeach
    </div>
@endif
