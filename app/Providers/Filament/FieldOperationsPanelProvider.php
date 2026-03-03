<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FieldOperationsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('field-operations')
            ->path('field-operations')
            ->brandName('PurchaseMaster')
            ->login()  
            ->default()
            ->darkMode(true)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->viteTheme('resources/css/app.css')
            ->maxContentWidth('full')
            ->spa()
            ->userMenuItems([
                MenuItem::make()
                    ->label('Admin Panel')
                    ->url('/admin')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->discoverResources(
                in: app_path('Filament/FieldOperations/Resources'),
                for: 'App\\Filament\\FieldOperations\\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/FieldOperations/Pages'), 
                for: 'App\\Filament\\FieldOperations\\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/FieldOperations/Widgets'), 
                for: 'App\\Filament\\FieldOperations\\Widgets'
            )
            ->navigationGroups([
                // ✅ Icons removed from groups to prevent conflicts with Resource icons
                NavigationGroup::make()->label('Shifts'),
                NavigationGroup::make()->label('Administration'),
                NavigationGroup::make()->label('Accounts'),
            ])
            ->pages([
                Dashboard::class,
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