<?php declare(strict_types=1); ?>

@if ($reports->isEmpty())
    <div class="assestme-generated-files__empty" data-dusk="generated-report-empty-state">
        <x-filament::icon icon="heroicon-o-document" />
        <p>{{ __('assestme.workspace.no_generated_files') }}</p>
    </div>
@else
    <div class="assestme-generated-files" data-dusk="generated-report-history">
        <table class="assestme-generated-files__table">
            <colgroup>
                <col class="assestme-generated-files__format-column">
                <col class="assestme-generated-files__name-column">
                <col class="assestme-generated-files__date-column">
                <col class="assestme-generated-files__size-column">
                <col class="assestme-generated-files__actions-column">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">{{ __('assestme.reports.history.format_version') }}</th>
                    <th scope="col">{{ __('assestme.reports.history.file_name') }}</th>
                    <th scope="col">{{ __('assestme.reports.history.generated_at') }}</th>
                    <th scope="col">{{ __('assestme.reports.history.size') }}</th>
                    <th scope="col" class="assestme-generated-files__actions-heading">{{ __('assestme.reports.history.actions') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($reports as $generatedReport)
                <tr data-generated-report-id="{{ $generatedReport->getKey() }}">
                    <td>
                        <span class="assestme-generated-files__format">
                            {{ strtoupper($generatedReport->format->value) }}
                            <span>v{{ str_pad((string) $generatedReport->version, 2, '0', STR_PAD_LEFT) }}</span>
                        </span>
                    </td>
                    <th scope="row" class="assestme-generated-files__name">{{ $generatedReport->file_name }}</th>
                    <td class="assestme-generated-files__nowrap">{{ $generatedReport->generated_at->timezone($timezone)->format('d/m/Y H:i') }}</td>
                    <td class="assestme-generated-files__nowrap">{{ \Illuminate\Support\Number::fileSize($generatedReport->file_size_bytes, precision: 1) }}</td>
                    <td>
                        <div class="assestme-generated-files__actions" data-report-id="{{ $generatedReport->getKey() }}">
                            <x-filament::button
                                tag="a"
                                :href="route('generated-reports.download', $generatedReport)"
                                icon="heroicon-o-arrow-down-tray"
                                color="gray"
                                size="sm"
                                outlined
                                data-dusk="download-generated-report"
                                data-report-id="{{ $generatedReport->getKey() }}"
                            >
                                {{ __('assestme.reports.download_existing') }}
                            </x-filament::button>
                            {{ ($this->deleteGeneratedReportAction)(['report' => $generatedReport->getKey()]) }}
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
