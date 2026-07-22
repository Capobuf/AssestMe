<?php declare(strict_types=1); ?>

@php
    $consultantLogo = null;
    $clientLogo = null;

    foreach ($report->logos as $logo) {
        if ($logo->owner === 'consultant') {
            $consultantLogo = $logo;
        } elseif ($logo->owner === 'client') {
            $clientLogo = $logo;
        }
    }

    $branding = (string) $report->setting('branding');
    $bottomLogo = $branding === 'client' ? $clientLogo : $consultantLogo;
    $bottomName = $branding === 'client'
        ? $report->clientName
        : ($report->setting('business_name') ?: $report->setting('consultant_name'));
    $bottomRole = $branding === 'client' ? null : $report->setting('consultant_role');
@endphp

<section class="cover" data-report-cover>
    <table class="cover__masthead">
        <tr>
            <td>{{ __('assestme.reports.document.report_mark') }}</td>
            <td class="cover__masthead-right">01</td>
        </tr>
    </table>
    <div class="cover__accent"></div>

    <div class="cover__center">
        <h1 class="cover__title">{{ $report->title }}</h1>

        @if ($report->setting('cover_title_mode') === 'separate')
            <h2 class="cover__client">{{ $report->clientName }}</h2>
        @endif

        <div class="cover__date">{{ $report->assessmentDateLabel }}</div>

        @if ($branding === 'both' && $clientLogo !== null)
            <img class="cover__client-logo" src="{{ $clientLogo->dataUri }}" alt="">
        @endif
    </div>

    <table class="cover__footer">
        <tr>
            @if ($bottomLogo !== null)
                <td style="width: {{ $branding === 'client' ? '44mm' : '56mm' }}">
                    <img
                        class="{{ $branding === 'client' ? 'cover__footer-client-logo' : 'cover__footer-logo' }}"
                        src="{{ $bottomLogo->dataUri }}"
                        alt=""
                    >
                </td>
            @endif
            @if (filled($bottomName) || filled($bottomRole))
                <td class="cover__footer-copy">
                    @if (filled($bottomName))
                        <div class="cover__footer-name">{{ $bottomName }}</div>
                    @endif
                    @if (filled($bottomRole))
                        <div class="cover__footer-role">{{ $bottomRole }}</div>
                    @endif
                </td>
            @endif
        </tr>
    </table>
</section>

<div class="page-break"></div>
