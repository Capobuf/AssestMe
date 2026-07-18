<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('assestme.dashboard.application_status') }}</x-slot>

        <div class="overflow-x-auto" data-dusk="application-status">
            <table class="w-full text-left text-sm" style="min-width: 48rem">
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $row)
                <tr>
                    <th scope="row" class="py-3 pr-4 font-medium">{{ $row['label'] }}</th>
                    <td class="py-3 pr-4">
                        <div class="flex items-center gap-2">
                        @if ($row['status'] === 'ok')
                            <x-filament::icon icon="heroicon-m-check-circle" class="h-5 w-5 text-success-600" />
                        @elseif ($row['status'] === 'failed')
                            <x-filament::icon icon="heroicon-m-x-circle" class="h-5 w-5 text-danger-600" />
                        @elseif ($row['status'] === 'warning')
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-warning-600" />
                        @else
                            <x-filament::icon icon="heroicon-m-question-mark-circle" class="h-5 w-5 text-gray-500" />
                        @endif
                        <span>{{ $row['status_label'] }}</span>
                        </div>
                    </td>
                    <td class="py-3 pr-4 whitespace-nowrap text-gray-500">{{ $row['event'] }}</td>
                    <td class="py-3 text-gray-600 dark:text-gray-300">{{ $row['message'] }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
