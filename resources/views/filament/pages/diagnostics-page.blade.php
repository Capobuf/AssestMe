<x-filament-panels::page>
    <section class="space-y-6" data-dusk="application-diagnostics">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">{{ __('assestme.diagnostics.runtime_heading') }}</h2>
            <p class="mt-2">PHP {{ $report['runtime']['php'] }} · {{ $report['runtime']['memory_limit'] }}</p>
            <p class="mt-1">{{ __('assestme.diagnostics.database', [
                'driver' => $report['database']['driver'],
                'product' => $report['database']['product'] ?? '—',
                'version' => $report['database']['server_version'] ?? '—',
            ]) }}</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="p-3">{{ __('assestme.diagnostics.columns.check') }}</th>
                        <th class="p-3">{{ __('assestme.diagnostics.columns.status') }}</th>
                        <th class="p-3">{{ __('assestme.diagnostics.columns.detail') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($report['checks'] as $key => $check)
                        <tr>
                            <th class="p-3 font-medium">{{ __("assestme.diagnostics.checks.{$key}") }}</th>
                            <td class="p-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                    'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $check['status'] === 'passed',
                                    'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => $check['status'] === 'failed',
                                    'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' => $check['status'] === 'warning',
                                ])>
                                    {{ __("assestme.diagnostics.status.{$check['status']}") }}
                                </span>
                            </td>
                            <td class="p-3 break-words">{{ $check['detail'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-filament-panels::page>
