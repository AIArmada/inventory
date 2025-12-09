<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentChip\FilamentChipPlugin;
use AIArmada\FilamentInventory\FilamentInventoryPlugin;
use AIArmada\FilamentJnt\FilamentJntPlugin;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentStock\FilamentStockPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
                'danger' => Color::Rose,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->font('Inter')
            ->brandName('Commerce Demo')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Commerce')
                    ->collapsed(false),
                NavigationGroup::make()
                    ->label('Inventory'),
                NavigationGroup::make()
                    ->label('Marketing'),
                NavigationGroup::make()
                    ->label('Payments'),
                NavigationGroup::make()
                    ->label('Shipping'),
                NavigationGroup::make()
                    ->label('Settings')
                    ->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
            ])
            ->plugins([
                FilamentCartPlugin::make(),
                FilamentVouchersPlugin::make(),
                FilamentStockPlugin::make(),
                FilamentInventoryPlugin::make(),
                FilamentAffiliatesPlugin::make(),
                FilamentChipPlugin::make(),
                FilamentJntPlugin::make(),
                FilamentAuthzPlugin::make(),
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
