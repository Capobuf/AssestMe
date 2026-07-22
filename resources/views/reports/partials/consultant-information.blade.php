<?php declare(strict_types=1); ?>

@php
    $consultantInformation = [];
    $consultantFields = [
        'consultant_name' => 'name',
        'business_name' => 'business_name',
        'consultant_role' => 'role',
        'consultant_email' => 'email',
        'consultant_phone' => 'phone',
        'consultant_website' => 'website',
        'consultant_address' => 'address',
        'consultant_vat_number' => 'vat_number',
        'consultant_pec' => 'pec',
        'consultant_tax_code' => 'tax_code',
    ];

    foreach ($consultantFields as $settingKey => $labelKey) {
        $value = $report->setting($settingKey);
        if (filled($value)) {
            $consultantInformation[] = [
                'label' => __('assestme.reports.document.'.$labelKey),
                'value' => $value,
                'url' => $settingKey === 'consultant_website',
            ];
        }
    }
@endphp

@if ($consultantInformation !== [])
    <section class="consultant-information">
        <h2 class="consultant-information__title">{{ __('assestme.reports.document.consultant_information') }}</h2>
        <table class="consultant-information__grid">
            @foreach (array_chunk($consultantInformation, 2) as $row)
                <tr>
                    @foreach ($row as $item)
                        <td>
                            <span class="label">{{ $item['label'] }}</span>
                            @if ($item['url'])
                                <a href="{{ $item['value'] }}">{{ $item['value'] }}</a>
                            @else
                                <div class="pre-line">{{ $item['value'] }}</div>
                            @endif
                        </td>
                    @endforeach
                    @if (count($row) === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </section>
@endif
