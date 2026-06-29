<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('')
            ->font('Inter')
            ->colors([
                'primary' => Color::Rose,
                'gray' => Color::Slate,
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => \Illuminate\Support\Facades\Blade::render('
                    <style>
                        @import url("https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap");

                        .fi-simple-main {
                            background-image: url("https://images.unsplash.com/photo-1534447677768-be436bb09401?q=80&w=2000&auto=format&fit=crop") !important;
                            background-size: cover !important;
                            background-position: center !important;
                            background-attachment: fixed !important;
                        }
                        .fi-simple-main::before {
                            content: "";
                            position: absolute;
                            inset: 0;
                            background: radial-gradient(circle at top right, rgba(30,27,75,0.8), rgba(15,23,42,0.9) 50%, rgba(2,6,23,0.95));
                        }
                        .fi-simple-main > * {
                            position: relative;
                            z-index: 10;
                        }
                        
                        /* Card estilo Glassmorphism */
                        .fi-simple-main > main > div,
                        .fi-simple-main > div > main > div,
                        .fi-simple-page > section {
                            background: rgba(30, 41, 59, 0.3) !important;
                            backdrop-filter: blur(20px) !important;
                            -webkit-backdrop-filter: blur(20px) !important;
                            border: 1px solid rgba(255, 255, 255, 0.1) !important;
                            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255,255,255,0.1) !important;
                            border-radius: 24px !important;
                        }
                        
                        /* Textos */
                        .fi-simple-main h1, .fi-simple-main label, .fi-simple-main p, .fi-simple-main a, .fi-simple-main span {
                            color: #f8fafc !important;
                        }
                        
                        /* Logo romántico */
                        .fi-simple-main .fi-logo {
                            color: transparent !important;
                            background: linear-gradient(to right, #f43f5e, #fda4af) !important;
                            -webkit-background-clip: text !important;
                            font-family: "Dancing Script", cursive !important;
                            font-size: 3rem !important;
                            line-height: 1.2 !important;
                            text-shadow: 0 10px 20px rgba(244, 63, 94, 0.3);
                        }
                        
                        /* Inputs */
                        .fi-simple-main input[type="email"], 
                        .fi-simple-main input[type="password"],
                        .fi-simple-main .fi-input-wrapper {
                            background: rgba(0, 0, 0, 0.2) !important;
                            border: 1px solid rgba(255, 255, 255, 0.1) !important;
                            color: white !important;
                            border-radius: 12px !important;
                            box-shadow: none !important;
                        }
                        .fi-simple-main input[type="email"]:focus, 
                        .fi-simple-main input[type="password"]:focus,
                        .fi-simple-main .fi-input-wrapper:focus-within {
                            border-color: #f43f5e !important;
                            box-shadow: 0 0 15px rgba(244, 63, 94, 0.3) !important;
                            background: rgba(0, 0, 0, 0.4) !important;
                        }
                        
                        /* Botón */
                        .fi-simple-main button[type="submit"] {
                            background: linear-gradient(to right, #f43f5e, #fb7185) !important;
                            border: none !important;
                            box-shadow: 0 8px 20px rgba(244, 63, 94, 0.4) !important;
                            border-radius: 12px !important;
                            text-transform: uppercase;
                            font-weight: 800 !important;
                            color: white !important;
                        }
                    </style>
                ')
            )
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
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
