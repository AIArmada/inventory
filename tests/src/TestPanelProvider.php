<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests;

use AIArmada\FilamentAffiliates\FilamentAffiliates;
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => '#6366f1',
            ])
            ->discoverResources(in: __DIR__ . '/../../packages/filament-chip/src/Resources', for: 'AIArmada\\FilamentChip\\Resources')
            ->discoverResources(in: __DIR__ . '/../../packages/filament-authz/src/Resources', for: 'AIArmada\\FilamentAuthz\\Resources')
            ->discoverPages(in: __DIR__ . '/../../packages/filament-chip/src/Pages', for: 'AIArmada\\FilamentChip\\Pages')
            ->discoverWidgets(in: __DIR__ . '/../../packages/filament-chip/src/Widgets', for: 'AIArmada\\FilamentChip\\Widgets')
            ->plugins([
                FilamentCartPlugin::make(),
                FilamentVouchersPlugin::make(),
                FilamentAffiliates::make(),
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
