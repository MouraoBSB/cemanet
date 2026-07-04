<?php

namespace App\Providers\Filament;

use App\Http\Controllers\MidiaController;
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
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Registra o módulo JS da extensão TipTap de alinhamento de imagem.
        // Carregado por demanda via RichContentPlugin::getTipTapJsExtensions().
        FilamentAsset::register([
            Js::make('imagem-alinhada', resource_path('js/filament/imagem-alinhada.js'))
                ->loadedOnRequest(),
            Js::make('texto-alinhado', resource_path('js/filament/texto-alinhado.js'))
                ->loadedOnRequest(),
            Js::make('colar-na-biblioteca', resource_path('js/filament/colar-na-biblioteca.js'))
                ->loadedOnRequest(),
            Css::make('cema-editor', resource_path('css/filament/editor.css')),
        ], package: 'app');
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authenticatedRoutes(fn () => Route::post(
                '/midia/colar',
                [MidiaController::class, 'colar'],
            )->name('midia.colar'))
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(false)
            ->brandLogo(asset('images/logos/logo-horizontal.png'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/logos/logo-icone.png'))
            ->colors([
                'primary' => Color::hex('#4E4483'),
                'info' => Color::hex('#6E9FCB'),
                'warning' => Color::hex('#F2A81E'),
                'danger' => Color::hex('#C33A36'),
                'success' => Color::hex('#008000'),
                'gray' => Color::Neutral,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
