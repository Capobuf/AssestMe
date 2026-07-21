<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Leek\FilamentRightClick\FilamentRightClickPlugin;
use OccTherapist\AdvancedTableExportForFilament\AdvancedTableExportForFilamentPlugin;

class AdminPanelProvider extends PanelProvider
{
    /** @var array<int, string> */
    private const PRIMARY_PALETTE = [
        50 => '#FCFFE6',
        100 => '#F8FFBF',
        200 => '#F1FF7A',
        300 => '#EBFF3D',
        400 => '#E6FF22',
        500 => '#E1FB15',
        600 => '#C9E20F',
        700 => '#A6BC08',
        800 => '#7D8E05',
        900 => '#566203',
        950 => '#2C3300',
    ];

    /** @var array<int, string> */
    private const SUCCESS_PALETTE = [
        50 => '#ECFDF5',
        100 => '#D1FAE5',
        200 => '#A7F3D0',
        300 => '#6EE7B7',
        400 => '#4ADE9B',
        500 => '#32D583',
        600 => '#20B96D',
        700 => '#178E55',
        800 => '#146C43',
        900 => '#125637',
        950 => '#082F20',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(EditProfile::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->recoveryCodeCount(8),
            ])
            ->brandName(__('assestme.app.name'))
            ->darkMode()
            ->assets([
                Css::make('assestme-workspace', resource_path('css/assestme-workspace.css')),
                Js::make('assestme-workspace', resource_path('js/assestme-workspace.js')),
            ])
            ->colors([
                'gray' => Color::Neutral,
                'primary' => self::PRIMARY_PALETTE,
                'success' => self::SUCCESS_PALETTE,
            ])
            ->plugins([
                FilamentRightClickPlugin::make(),
                AdvancedTableExportForFilamentPlugin::make()
                    ->maxExportRows(2000)
                    ->previewPerPage(25),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
