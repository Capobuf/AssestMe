@php
    $heading = $page->getHeading();
    $headerActions = $page->getCachedHeaderActions();
    $headerActionsAlignment = $page->getHeaderActionsAlignment();
    $breadcrumbs = filament()->hasBreadcrumbs() ? $page->getBreadcrumbs() : [];
    $subheading = $page->getSubheading();
    $settingsNavigation = app(\App\Filament\Clusters\SettingsCluster::class)->getCachedSubNavigation();
@endphp

@if (filled($headerActions) || $breadcrumbs || filled($heading) || filled($subheading))
    <x-filament-panels::header
        :actions="$headerActions"
        :actions-alignment="$headerActionsAlignment"
        :breadcrumbs="$breadcrumbs"
        :heading="$heading"
        :subheading="$subheading"
    >
        @if ($heading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="heading">
                {{ $heading }}
            </x-slot>
        @endif

        @if ($subheading instanceof \Illuminate\Contracts\Support\Htmlable)
            <x-slot name="subheading">
                {{ $subheading }}
            </x-slot>
        @endif
    </x-filament-panels::header>
@endif

<div class="assestme-settings-parent-navigation">
    <x-filament-panels::page.sub-navigation.mobile-menu
        :navigation="$settingsNavigation"
        class="assestme-settings-parent-navigation__dropdown"
    />

    <x-filament-panels::page.sub-navigation.tabs
        :navigation="$settingsNavigation"
        class="assestme-settings-parent-navigation__tabs"
    />
</div>
