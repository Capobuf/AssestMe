<x-filament-widgets::widget class="assestme-dashboard-widget">
    <div class="assestme-dashboard" data-dusk="operational-dashboard">
        <section class="assestme-dashboard-hero" aria-labelledby="assestme-dashboard-hero-title" data-dusk="dashboard-hero">
            <img
                class="assestme-dashboard-hero__logo"
                src="{{ asset('images/brand/assestme-logo-white.svg') }}"
                alt=""
                aria-hidden="true"
            >

            <div class="assestme-dashboard-hero__content">
                <div class="assestme-dashboard-hero__intro">
                    <span class="assestme-dashboard-eyebrow">{{ __('assestme.app.name') }}</span>
                    <h2 id="assestme-dashboard-hero-title">{{ __('assestme.dashboard.resume.heading') }}</h2>
                    <p>{{ __('assestme.dashboard.resume.introduction') }}</p>
                </div>

                <div class="assestme-dashboard-resume" data-dusk="dashboard-resume">
                    @if ($resumeAssessment !== null)
                        <div class="assestme-dashboard-resume__body">
                            <span class="assestme-dashboard-resume__label">{{ __('assestme.dashboard.resume.suggested') }}</span>
                            <strong>{{ $resumeAssessment->client->displayName() }}</strong>
                            <span>
                                {{ $resumeAssessment->title }} · {{ $resumeAssessment->assessment_date->format('d/m/Y') }}
                            </span>
                            <span class="assestme-dashboard-status assestme-dashboard-status--{{ $resumeAssessment->status->value }}">
                                {{ __('assestme.assessments.status.'.$resumeAssessment->status->value) }}
                            </span>
                        </div>
                        <a class="assestme-dashboard-resume__action" href="{{ $resumeUrl }}">
                            <span>{{ __('assestme.dashboard.resume.open_workspace') }}</span>
                            <x-filament::icon icon="heroicon-m-arrow-right" aria-hidden="true" />
                        </a>
                    @else
                        <div class="assestme-dashboard-resume__body">
                            <span class="assestme-dashboard-resume__label">{{ __('assestme.dashboard.resume.empty_label') }}</span>
                            <strong>{{ __('assestme.dashboard.resume.empty_title') }}</strong>
                            <span>{{ __('assestme.dashboard.resume.empty_help') }}</span>
                        </div>
                        <a class="assestme-dashboard-resume__action" href="{{ $resumeUrl }}">
                            <span>{{ __('assestme.dashboard.resume.start_first') }}</span>
                            <x-filament::icon icon="heroicon-m-arrow-right" aria-hidden="true" />
                        </a>
                    @endif
                </div>

                <nav class="assestme-dashboard-launchers" aria-label="{{ __('assestme.dashboard.quick_access') }}">
                    @foreach ($launchers as $launcher)
                        <a
                            class="assestme-dashboard-launcher{{ $launcher['primary'] ? ' assestme-dashboard-launcher--primary' : '' }}"
                            href="{{ $launcher['url'] }}"
                            data-dusk="dashboard-launcher-{{ $loop->index }}"
                        >
                            <span class="assestme-dashboard-launcher__icon">
                                <x-filament::icon :icon="$launcher['icon']" aria-hidden="true" />
                            </span>
                            <span class="assestme-dashboard-launcher__copy">
                                <strong>{{ $launcher['title'] }}</strong>
                                <span>{{ $launcher['description'] }}</span>
                            </span>
                            <x-filament::icon class="assestme-dashboard-launcher__arrow" icon="heroicon-m-chevron-right" aria-hidden="true" />
                        </a>
                    @endforeach
                </nav>
            </div>
        </section>

        <div class="assestme-dashboard-primary-grid">
            <section class="assestme-dashboard-panel" aria-labelledby="assestme-dashboard-companies-title" data-dusk="recent-companies">
                <header class="assestme-dashboard-panel__header">
                    <div>
                        <span class="assestme-dashboard-panel__eyebrow">{{ __('assestme.dashboard.companies.eyebrow') }}</span>
                        <h2 id="assestme-dashboard-companies-title">{{ __('assestme.dashboard.recent_companies') }}</h2>
                    </div>
                    <a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('index') }}">
                        {{ __('assestme.dashboard.view_all') }}
                    </a>
                </header>

                <div class="assestme-dashboard-list">
                    @forelse ($recentCompanies as $company)
                        @php($latestAssessment = $company['latest_assessment'])
                        <a class="assestme-dashboard-list-row" href="{{ $company['url'] }}">
                            <span class="assestme-dashboard-company-mark" aria-hidden="true">{{ $company['initials'] }}</span>
                            <span class="assestme-dashboard-list-row__copy">
                                <strong>{{ $company['name'] }}</strong>
                                @if ($latestAssessment !== null)
                                    <span>{{ $latestAssessment->title }}</span>
                                    <small>
                                        {{ __('assestme.dashboard.companies.latest_on', ['date' => $latestAssessment->assessment_date->format('d/m/Y')]) }}
                                        · {{ __('assestme.assessments.status.'.$latestAssessment->status->value) }}
                                    </small>
                                @else
                                    <span>{{ __('assestme.dashboard.companies.no_assessment') }}</span>
                                @endif
                            </span>
                            <x-filament::icon class="assestme-dashboard-list-row__arrow" icon="heroicon-m-chevron-right" aria-hidden="true" />
                        </a>
                    @empty
                        <div class="assestme-dashboard-empty">
                            <x-filament::icon icon="heroicon-o-building-office-2" aria-hidden="true" />
                            <strong>{{ __('assestme.dashboard.companies.empty_title') }}</strong>
                            <span>{{ __('assestme.dashboard.companies.empty_help') }}</span>
                            <a href="{{ \App\Filament\Resources\Clients\ClientResource::getUrl('create') }}">
                                {{ __('assestme.dashboard.companies.create_first') }}
                            </a>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="assestme-dashboard-panel" aria-labelledby="assestme-dashboard-assessments-title" data-dusk="latest-assessments">
                <header class="assestme-dashboard-panel__header">
                    <div>
                        <span class="assestme-dashboard-panel__eyebrow">{{ __('assestme.dashboard.assessments.eyebrow') }}</span>
                        <h2 id="assestme-dashboard-assessments-title">{{ __('assestme.dashboard.latest_assessments') }}</h2>
                    </div>
                    <a href="{{ \App\Filament\Resources\Assessments\AssessmentResource::getUrl('index') }}">
                        {{ __('assestme.dashboard.more_assessments') }}
                    </a>
                </header>

                <div class="assestme-dashboard-list">
                    @forelse ($latestAssessments as $assessment)
                        <a
                            class="assestme-dashboard-list-row assestme-dashboard-list-row--assessment"
                            href="{{ \App\Filament\Resources\Assessments\Pages\WorkspaceAssessment::getUrl(['record' => $assessment]) }}"
                        >
                            <span class="assestme-dashboard-assessment-icon" aria-hidden="true">
                                <x-filament::icon icon="heroicon-o-clipboard-document-check" />
                            </span>
                            <span class="assestme-dashboard-list-row__copy">
                                <strong>{{ $assessment->client->displayName() }}</strong>
                                <span>{{ __('assestme.scopes.'.$assessment->scope_type->value) }}</span>
                            </span>
                            <span class="assestme-dashboard-list-row__meta">
                                <span class="assestme-dashboard-status assestme-dashboard-status--{{ $assessment->status->value }}">
                                    {{ __('assestme.assessments.status.'.$assessment->status->value) }}
                                </span>
                                <time datetime="{{ $assessment->assessment_date->toDateString() }}">
                                    {{ $assessment->assessment_date->format('d/m/Y') }}
                                </time>
                            </span>
                            <x-filament::icon class="assestme-dashboard-list-row__arrow" icon="heroicon-m-chevron-right" aria-hidden="true" />
                        </a>
                    @empty
                        <div class="assestme-dashboard-empty">
                            <x-filament::icon icon="heroicon-o-clipboard-document-check" aria-hidden="true" />
                            <strong>{{ __('assestme.dashboard.assessments.empty_title') }}</strong>
                            <span>{{ __('assestme.dashboard.assessments.empty_help') }}</span>
                            <a href="{{ \App\Filament\Resources\Assessments\AssessmentResource::getUrl('create') }}">
                                {{ __('assestme.dashboard.resume.start_first') }}
                            </a>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <section class="assestme-dashboard-archive" aria-labelledby="assestme-dashboard-archive-title" data-dusk="dashboard-archive">
            <header class="assestme-dashboard-archive__header">
                <span class="assestme-dashboard-panel__eyebrow">{{ __('assestme.dashboard.archive.eyebrow') }}</span>
                <h2 id="assestme-dashboard-archive-title">{{ __('assestme.dashboard.archive.heading') }}</h2>
            </header>

            <div class="assestme-dashboard-archive__grid">
                @foreach ($archiveItems as $item)
                    <a class="assestme-dashboard-archive-item" href="{{ $item['url'] }}">
                        <span class="assestme-dashboard-archive-item__icon">
                            <x-filament::icon :icon="$item['icon']" aria-hidden="true" />
                        </span>
                        <span class="assestme-dashboard-archive-item__copy">
                            <strong>{{ $item['label'] }}</strong>
                            <span>{{ $item['description'] }}</span>
                        </span>
                        <span class="assestme-dashboard-archive-item__count">{{ $item['count'] }}</span>
                        <x-filament::icon class="assestme-dashboard-archive-item__arrow" icon="heroicon-m-arrow-right" aria-hidden="true" />
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
