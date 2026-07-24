<?php declare(strict_types=1); ?>

@php
    $rawPrimaryColor = (string) ($get('primary_color') ?: '#2563EB');
    $primaryColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimaryColor) === 1
        ? mb_strtoupper($rawPrimaryColor)
        : '#111111';
    $company = __('assestme.settings.preview.company');
    $configuredTitle = trim((string) ($get('default_title_pattern') ?: __('assestme.settings.preview.title')));
    $baseTitle = trim(str_replace('{client}', '', $configuredTitle));
    $baseTitle = trim((string) preg_replace('/(?:\s*[—-]\s*)+$/u', '', $baseTitle));
    $baseTitle = $baseTitle === '' ? __('assestme.settings.preview.title') : $baseTitle;
    $combinedTitle = str_contains(mb_strtolower($baseTitle), mb_strtolower($company))
        ? $baseTitle
        : $baseTitle.' — '.$company;

    $coverTitle = $get('cover_title_mode') === 'combined' ? $combinedTitle : $baseTitle;
    $branding = (string) ($get('branding') ?: 'consultant');
    $consultant = $get('business_name') ?: $get('consultant_name') ?: __('assestme.settings.preview.consultant');
    $header = $get('header_text') ?: __('assestme.settings.preview.header_fallback');
    $footer = $get('footer_text') ?: $get('business_name') ?: $get('consultant_name') ?: __('assestme.settings.preview.application_name');
@endphp

<style>
    .assestme-report-preview-column { min-width: 0; }
    .assestme-report-preview {
        position: sticky;
        top: 5.5rem;
    }
    .assestme-report-preview__intro {
        color: rgb(113 113 122);
        font-size: 0.8rem;
        line-height: 1.4;
        margin-bottom: 0.75rem;
    }
    .assestme-report-preview__tabs {
        border-bottom: 1px solid rgb(212 212 216);
        display: table;
        margin-bottom: 0.75rem;
        table-layout: fixed;
        width: 100%;
    }
    .assestme-report-preview__tabs button {
        background: transparent;
        border: 0;
        color: inherit;
        cursor: pointer;
        display: table-cell;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.55rem 0.35rem;
        text-align: center;
        width: 50%;
    }
    .assestme-report-preview__tabs button[aria-selected="true"] { border-bottom: 2px solid var(--preview-accent); }
    .assestme-report-preview__sheet {
        background: #FFFFFF;
        border: 1px solid #D7D7D2;
        color: #111111;
        font-family: "DejaVu Sans", Arial, sans-serif;
        min-height: 31rem;
        padding: 1rem;
    }
    .assestme-report-preview__topline,
    .assestme-report-preview__footer {
        border-bottom: 1px solid #111111;
        display: table;
        font-size: 0.54rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        padding-bottom: 0.35rem;
        width: 100%;
    }
    .assestme-report-preview__topline span,
    .assestme-report-preview__footer span { display: table-cell; }
    .assestme-report-preview__topline span:last-child,
    .assestme-report-preview__footer span:last-child { text-align: right; }
    .assestme-report-preview__accent { border-top: 3px solid var(--preview-accent); margin-top: 0.4rem; width: 2rem; }
    .assestme-report-preview__cover-copy { margin-top: 6.5rem; width: 88%; }
    .assestme-report-preview__cover-title { font-size: 1.85rem; font-weight: 800; line-height: 0.98; overflow-wrap: anywhere; }
    .assestme-report-preview__company { font-size: 0.9rem; margin-top: 0.75rem; }
    .assestme-report-preview__date { color: #666666; font-size: 0.62rem; margin-top: 0.4rem; }
    .assestme-report-preview__cover-footer { border-top: 1px solid #111111; margin-top: 8rem; padding-top: 0.75rem; }
    .assestme-report-preview__client-brand { border-left: 2px solid var(--preview-accent); font-size: 0.58rem; margin-top: 0.65rem; padding-left: 0.4rem; }
    .assestme-report-preview__brand { font-size: 0.7rem; font-weight: 700; }
    .assestme-report-preview__internal-header { border-bottom: 1px solid #D7D7D2; font-size: 0.54rem; padding-bottom: 0.3rem; }
    .assestme-report-preview__section { display: table; margin-top: 1rem; width: 100%; }
    .assestme-report-preview__section-number { display: table-cell; font-size: 2rem; font-weight: 800; line-height: 1; padding-right: 0.35rem; vertical-align: top; width: 2.2rem; }
    .assestme-report-preview__section-title { border-left: 2px solid var(--preview-accent); display: table-cell; font-size: 1.15rem; font-weight: 800; line-height: 1.05; padding: 0 0 0 0.55rem; vertical-align: top; }
    .assestme-report-preview__kpis { border: 0; display: table; margin-top: 0.6rem; width: 100%; }
    .assestme-report-preview__kpi { border-left: 1px solid #D7D7D2; display: table-cell; padding: 0.35rem 0.45rem; }
    .assestme-report-preview__kpi:first-child { border-left: 0; }
    .assestme-report-preview__kpi strong { display: block; font-size: 1rem; }
    .assestme-report-preview__kpi span { color: #666666; font-size: 0.48rem; text-transform: uppercase; }
    .assestme-report-preview__information { background: #F3F3EF; display: table; font-size: 0.55rem; margin-top: 0.55rem; table-layout: fixed; width: 100%; }
    .assestme-report-preview__information > span { display: table-cell; padding: 0.4rem; width: 50%; }
    .assestme-report-preview__information strong { color: #666666; display: block; font-size: 0.44rem; letter-spacing: 0.06em; text-transform: uppercase; }
    .assestme-report-preview__legend { display: table; font-size: 0.52rem; margin-top: 0.55rem; width: auto; }
    .assestme-report-preview__legend > strong,
    .assestme-report-preview__legend > span { border: 0; display: table-cell; padding-right: 0.7rem; white-space: nowrap; }
    .assestme-report-preview__legend > strong { color: #666666; font-size: 0.46rem; letter-spacing: 0.07em; text-transform: uppercase; }
    .assestme-report-preview__glyph { font-family: "DejaVu Sans", Arial, sans-serif; }
    .assestme-report-preview__finding { border: 0; display: table; margin-top: 0.9rem; width: 100%; }
    .assestme-report-preview__finding-number { border-top: 1px solid #111111; display: table-cell; font-size: 1.1rem; font-weight: 800; padding: 0.45rem; width: 2rem; }
    .assestme-report-preview__finding-copy { border-top: 1px solid #111111; display: table-cell; font-size: 0.58rem; padding: 0.45rem; }
    .assestme-report-preview__finding-copy strong { display: block; font-size: 0.68rem; }
    .assestme-report-preview__status { display: inline-block; white-space: nowrap; }
    .assestme-report-preview__note { background: #F3F3EF; border-left: 3px solid var(--preview-accent); font-size: 0.62rem; line-height: 1.45; margin: 0.7rem 0; padding: 0.55rem 0.65rem; }
    .assestme-report-preview__note strong { color: var(--preview-accent); display: block; font-size: 0.48rem; letter-spacing: 0.05em; margin-bottom: 0.25rem; text-transform: uppercase; }
    .assestme-report-preview__solution { border-left: 2px solid var(--preview-accent); font-size: 0.55rem; margin-top: 0.65rem; padding-left: 0.5rem; }
    .assestme-report-preview__solution strong { display: block; font-size: 0.46rem; letter-spacing: 0.06em; text-transform: uppercase; }
    .assestme-report-preview__solution-title { font-size: 0.68rem; font-weight: 700; margin: 0.2rem 0; }
    .assestme-report-preview__scope { font-size: 0.55rem; margin-top: 0.8rem; }
    .assestme-report-preview__scope strong,
    .assestme-report-preview__risk strong { color: #666666; display: block; font-size: 0.45rem; letter-spacing: 0.06em; text-transform: uppercase; }
    .assestme-report-preview__risk-title { font-size: 0.5rem; font-weight: 800; letter-spacing: 0.07em; margin-top: 0.65rem; text-transform: uppercase; }
    .assestme-report-preview__risk { display: table; font-size: 0.58rem; margin-top: 0.35rem; table-layout: fixed; width: 100%; }
    .assestme-report-preview__risk > span { border: 0; display: table-cell; padding: 0.35rem 0.4rem; width: 30%; }
    .assestme-report-preview__risk > span + span { border-left: 1px solid #D7D7D2; }
    .assestme-report-preview__risk > span:last-child { font-weight: 700; width: 40%; }
    .assestme-report-preview__metrics { border-bottom: 1px solid #D7D7D2; border-top: 1px solid #D7D7D2; display: table; font-size: 0.55rem; width: 100%; }
    .assestme-report-preview__metrics span { border: 0; display: table-cell; padding: 0.4rem; }
    .assestme-report-preview__metrics span + span { border-left: 1px solid #D7D7D2; }
    .assestme-report-preview__asset { border-top: 1px solid #D7D7D2; font-size: 0.52rem; margin-top: 0.65rem; padding-top: 0.35rem; }
    .assestme-report-preview__asset strong { display: block; font-size: 0.6rem; }
    .assestme-report-preview__asset span { color: #666666; display: block; margin-top: 0.1rem; }
    .assestme-report-preview__footer { border-bottom: 0; border-top: 1px solid #D7D7D2; color: #666666; font-weight: 400; margin-top: 1rem; padding: 0.35rem 0 0; }
    .assestme-report-preview__flags { color: #666666; font-size: 0.48rem; margin-top: 0.8rem; }
    .assestme-report-preview[data-preview-page="cover"] [data-dusk="report-preview-internal"],
    .assestme-report-preview[data-preview-page="internal"] [data-dusk="report-preview-cover"] { display: none; }
    @media (max-width: 1279px) {
        .assestme-report-preview { position: static; }
    }
</style>

<section
    class="assestme-report-preview"
    x-data="{}"
    data-preview-page="cover"
    data-dusk="report-style-preview"
    wire:ignore.self
>
    <div style="--preview-accent: {{ $primaryColor }}" data-dusk="report-preview-accent-scope">
    <h2 class="text-base font-semibold">{{ __('assestme.settings.preview.heading') }}</h2>
    <p class="assestme-report-preview__intro">{{ __('assestme.settings.preview.description') }}</p>

    <div class="assestme-report-preview__tabs" role="tablist" wire:ignore>
        <button
            type="button"
            role="tab"
            aria-selected="true"
            x-on:click="$el.closest('[data-dusk=report-style-preview]').dataset.previewPage = 'cover'; $el.setAttribute('aria-selected', 'true'); $el.parentElement.querySelector('[data-dusk=report-preview-internal-tab]').setAttribute('aria-selected', 'false')"
            data-dusk="report-preview-cover-tab"
        >{{ __('assestme.settings.preview.cover_tab') }}</button>
        <button
            type="button"
            role="tab"
            aria-selected="false"
            x-on:click="$el.closest('[data-dusk=report-style-preview]').dataset.previewPage = 'internal'; $el.setAttribute('aria-selected', 'true'); $el.parentElement.querySelector('[data-dusk=report-preview-cover-tab]').setAttribute('aria-selected', 'false')"
            data-dusk="report-preview-internal-tab"
        >{{ __('assestme.settings.preview.internal_tab') }}</button>
    </div>

    <div class="assestme-report-preview__sheet" data-dusk="report-preview-cover">
        @if ($get('cover') === true)
            <div class="assestme-report-preview__topline"><span>{{ __('assestme.reports.document.report_mark') }}</span><span>01</span></div>
            <div class="assestme-report-preview__accent"></div>
            <div class="assestme-report-preview__cover-copy">
                <div class="assestme-report-preview__cover-title" data-dusk="report-preview-title">{{ $coverTitle }}</div>
                @if ($get('cover_title_mode') !== 'combined')
                    <div class="assestme-report-preview__company">{{ $company }}</div>
                @endif
                <div class="assestme-report-preview__date">{{ __('assestme.settings.preview.date') }}</div>
                @if ($branding === 'both')
                    <div class="assestme-report-preview__client-brand">{{ $company }}</div>
                @endif
            </div>
            <div class="assestme-report-preview__cover-footer">
                <div class="assestme-report-preview__brand">
                    {{ $branding === 'client' ? $company : $consultant }}
                </div>
            </div>
        @else
            <div class="assestme-report-preview__intro">{{ __('assestme.settings.preview.cover_disabled') }}</div>
        @endif
    </div>

    <div class="assestme-report-preview__sheet" data-dusk="report-preview-internal">
        <div class="assestme-report-preview__internal-header" data-dusk="report-preview-header">{{ $header }}</div>
        <div class="assestme-report-preview__section" data-dusk="report-preview-section-heading">
            <div class="assestme-report-preview__section-number" data-dusk="report-preview-section-number">01</div>
            <div class="assestme-report-preview__section-title" data-dusk="report-preview-section-title">{{ __('assestme.settings.preview.overview') }}</div>
        </div>
        <div class="assestme-report-preview__kpis" data-dusk="report-preview-kpis">
            <div class="assestme-report-preview__kpi"><strong>03</strong><span>{{ __('assestme.settings.preview.total_findings') }}</span></div>
            <div class="assestme-report-preview__kpi">
                <strong>01</strong>
                <span><span class="assestme-report-preview__glyph" style="color: #B42318">●</span> {{ __('assestme.settings.preview.high_priority') }}</span>
            </div>
        </div>
        <div class="assestme-report-preview__information" data-dusk="report-preview-information">
            <span><strong>{{ __('assestme.reports.document.client') }}</strong>{{ $company }}</span>
            <span><strong>{{ __('assestme.reports.document.assessment_date') }}</strong>{{ __('assestme.settings.preview.date') }}</span>
        </div>

        @if ($get('risk_legend') === true)
            <div class="assestme-report-preview__legend" data-dusk="report-preview-priority-legend">
                <strong>{{ __('assestme.reports.document.priority_legend') }}</strong>
                <span><span class="assestme-report-preview__glyph" style="color: #3B7A57">●</span> {{ __('assestme.settings.preview.priority_low') }}</span>
                <span><span class="assestme-report-preview__glyph" style="color: #D97706">●</span> {{ __('assestme.settings.preview.priority_moderate') }}</span>
                <span><span class="assestme-report-preview__glyph" style="color: #B42318">●</span> {{ __('assestme.settings.preview.priority_high') }}</span>
            </div>
        @endif

        @if ($get('summary_table') === true)
            <div class="assestme-report-preview__finding">
                <div class="assestme-report-preview__finding-number">03</div>
                <div class="assestme-report-preview__finding-copy">
                    <strong>{{ __('assestme.settings.preview.finding_title') }}</strong>
                    <span class="assestme-report-preview__glyph" style="color: #B42318">●</span>
                    {{ __('assestme.settings.preview.priority') }}
                    &nbsp;&nbsp;
                    <span class="assestme-report-preview__status"><span class="assestme-report-preview__glyph">○</span>&nbsp;{{ __('assestme.settings.preview.status') }}</span>
                </div>
            </div>
        @endif

        <div class="assestme-report-preview__note">
            <strong>{{ __('assestme.settings.preview.management_note') }}</strong>
            {{ __('assestme.settings.preview.management_note_text') }}
        </div>

        <div class="assestme-report-preview__solution" data-dusk="report-preview-recommended-solution">
            <strong>{{ __('assestme.reports.document.recommended_solution') }}</strong>
            <div class="assestme-report-preview__solution-title">{{ __('assestme.settings.preview.solution_title') }}</div>
            {{ __('assestme.settings.preview.solution_text') }}
        </div>

        <div class="assestme-report-preview__scope">
            <strong>{{ __('assestme.reports.document.scope') }}</strong>
            {{ __('assestme.settings.preview.scope_value') }}
        </div>
        <div class="assestme-report-preview__risk-title" data-dusk="report-preview-risk-title">{{ __('assestme.reports.document.risk_evaluation') }}</div>
        <div class="assestme-report-preview__risk" data-dusk="report-preview-risk">
            <span>
                <strong>{{ __('assestme.reports.document.consequence') }}</strong>
                {{ __('assestme.settings.preview.consequence_value') }}
            </span>
            <span>
                <strong>{{ __('assestme.reports.document.likelihood') }}</strong>
                {{ __('assestme.settings.preview.likelihood_value') }}
            </span>
            <span>
                <strong>{{ __('assestme.reports.document.resulting_priority') }}</strong>
                <span class="assestme-report-preview__glyph" style="color: #B42318">●</span>
                {{ __('assestme.settings.preview.priority_high') }}
            </span>
        </div>

        <div class="assestme-report-preview__metrics">
            <span>{{ __('assestme.settings.preview.effort') }}</span>
            @if ($get('costs') === true)
                <span>{{ __('assestme.settings.preview.estimate') }}</span>
            @endif
        </div>
        <div class="assestme-report-preview__asset" data-dusk="report-preview-asset">
            <strong>{{ __('assestme.settings.preview.asset_name') }}</strong>
            <span>{{ __('assestme.settings.preview.asset_metadata') }}</span>
        </div>

        <div class="assestme-report-preview__flags">
            @if ($get('alternative_solutions') === true){{ __('assestme.settings.preview.alternatives_on') }} @endif
            @if ($get('evidence') === true){{ __('assestme.settings.preview.evidence_on') }}@endif
        </div>
        <div class="assestme-report-preview__footer">
            <span data-dusk="report-preview-footer">{{ $footer }}</span>
            <span>{{ __('assestme.settings.preview.page_number') }}</span>
        </div>
    </div>
    </div>
</section>
