<x-filament-panels::page>
    <div class="assestme-diagnostics" data-dusk="application-diagnostics">
        <x-filament::section
            icon="heroicon-o-server-stack"
            :heading="__('assestme.diagnostics.runtime_heading')"
        >
            <div class="assestme-diagnostics__summary">
                <div class="assestme-diagnostics__summary-item">
                    <span>{{ __('assestme.diagnostics.runtime.php') }}</span>
                    <strong>{{ $report['runtime']['php'] }}</strong>
                </div>

                <div class="assestme-diagnostics__summary-item">
                    <span>{{ __('assestme.diagnostics.runtime.memory') }}</span>
                    <strong>{{ $report['runtime']['memory_limit'] }}</strong>
                </div>

                <div class="assestme-diagnostics__summary-item">
                    <span>{{ __('assestme.diagnostics.runtime.database') }}</span>
                    <strong>{{ $report['database']['product'] ?? $report['database']['driver'] }}</strong>
                    <small>{{ $report['database']['server_version'] ?? '—' }}</small>
                </div>
            </div>
        </x-filament::section>

        <section class="assestme-diagnostics__checks" aria-labelledby="diagnostics-checks-heading">
            <div class="assestme-diagnostics__checks-heading">
                <div>
                    <h2 id="diagnostics-checks-heading">{{ __('assestme.diagnostics.checks_heading') }}</h2>
                    <p>{{ __('assestme.diagnostics.checks_description') }}</p>
                </div>

                <x-filament::badge :color="$report['ok'] ? 'success' : 'danger'">
                    {{ $report['ok']
                        ? __('assestme.diagnostics.status.passed')
                        : __('assestme.diagnostics.status.failed') }}
                </x-filament::badge>
            </div>

            <div class="assestme-diagnostics__table-scroll">
                <table class="assestme-diagnostics__table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('assestme.diagnostics.columns.check') }}</th>
                            <th scope="col">{{ __('assestme.diagnostics.columns.status') }}</th>
                            <th scope="col">{{ __('assestme.diagnostics.columns.detail') }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($report['checks'] as $key => $check)
                            @php
                                $statusColor = match ($check['status']) {
                                    'passed' => 'success',
                                    'failed' => 'danger',
                                    default => 'warning',
                                };
                            @endphp

                            <tr>
                                <th scope="row">{{ __("assestme.diagnostics.checks.{$key}") }}</th>
                                <td>
                                    <x-filament::badge :color="$statusColor">
                                        {{ __("assestme.diagnostics.status.{$check['status']}") }}
                                    </x-filament::badge>
                                </td>
                                <td class="assestme-diagnostics__detail">{{ $check['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
