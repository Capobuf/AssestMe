<?php declare(strict_types=1); ?>

@if ($reports->isEmpty())
    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/15" data-dusk="generated-report-empty-state">
        {{ __('assestme.workspace.no_generated_files') }}
    </div>
@else
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10" data-dusk="generated-report-history">
        <table class="w-full text-left text-sm" style="min-width: 48rem; table-layout: fixed">
            <colgroup>
                <col style="width: 14%">
                <col style="width: 30%">
                <col style="width: 18%">
                <col style="width: 12%">
                <col style="width: 26%">
            </colgroup>
            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2">{{ __('assestme.reports.history.format_version') }}</th>
                    <th class="px-3 py-2">{{ __('assestme.reports.history.file_name') }}</th>
                    <th class="px-3 py-2">{{ __('assestme.reports.history.generated_at') }}</th>
                    <th class="px-3 py-2">{{ __('assestme.reports.history.size') }}</th>
                    <th class="px-3 py-2 text-right">{{ __('assestme.reports.history.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($reports as $generatedReport)
                <tr data-generated-report-id="{{ $generatedReport->getKey() }}">
                    <td class="px-3 py-2 font-medium">{{ strtoupper($generatedReport->format->value) }} · v{{ str_pad((string) $generatedReport->version, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="px-3 py-2" style="overflow-wrap: anywhere">{{ $generatedReport->file_name }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ $generatedReport->generated_at->timezone($timezone)->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ \Illuminate\Support\Number::fileSize($generatedReport->file_size_bytes, precision: 1) }}</td>
                    <td class="px-3 py-2"><div class="flex items-center justify-end gap-2">
                    <a
                        href="{{ route('generated-reports.download', $generatedReport) }}"
                        data-dusk="download-generated-report"
                        data-report-id="{{ $generatedReport->getKey() }}"
                        class="fi-btn fi-btn-size-md fi-color fi-color-primary"
                    >
                        {{ __('assestme.reports.download_existing') }}
                    </a>
                    <div data-report-id="{{ $generatedReport->getKey() }}">
                        {{ ($this->deleteGeneratedReportAction)(['report' => $generatedReport->getKey()]) }}
                    </div>
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
