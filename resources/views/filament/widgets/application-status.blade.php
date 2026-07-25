<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('assestme.dashboard.application_status') }}</x-slot>

        <div class="assestme-application-status" data-dusk="application-status">
            <table
                class="assestme-application-status__table"
                aria-label="{{ __('assestme.dashboard.application_status') }}"
            >
                <thead>
                    <tr>
                        <th scope="col">{{ __('assestme.dashboard.check') }}</th>
                        <th scope="col">{{ __('assestme.dashboard.condition') }}</th>
                        <th scope="col">{{ __('assestme.dashboard.last_event') }}</th>
                        <th scope="col">{{ __('assestme.dashboard.details') }}</th>
                    </tr>
                </thead>

                <tbody>
                @foreach ($rows as $row)
                <tr class="assestme-application-status__row assestme-application-status__row--{{ $row['status'] }}">
                    <th
                        scope="row"
                        data-label="{{ __('assestme.dashboard.check') }}"
                    >
                        {{ $row['label'] }}
                    </th>

                    <td
                        class="assestme-application-status__condition"
                        data-label="{{ __('assestme.dashboard.condition') }}"
                    >
                        <div class="assestme-application-status__condition-content">
                        @if ($row['status'] === 'ok')
                            <x-filament::icon icon="heroicon-m-check-circle" class="assestme-application-status__icon" />
                        @elseif ($row['status'] === 'failed')
                            <x-filament::icon icon="heroicon-m-x-circle" class="assestme-application-status__icon" />
                        @elseif ($row['status'] === 'warning')
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="assestme-application-status__icon" />
                        @else
                            <x-filament::icon icon="heroicon-m-question-mark-circle" class="assestme-application-status__icon" />
                        @endif

                            <span>{{ $row['status_label'] }}</span>
                        </div>
                    </td>

                    <td
                        class="assestme-application-status__event"
                        data-label="{{ __('assestme.dashboard.last_event') }}"
                    >
                        {{ $row['event'] }}
                    </td>

                    <td
                        class="assestme-application-status__message"
                        data-label="{{ __('assestme.dashboard.details') }}"
                    >
                        {{ $row['message'] }}
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
