<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use AIArmada\FilamentAffiliates\FilamentAffiliatesPlugin;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentCart\FilamentCartPlugin;
use AIArmada\FilamentCashier\FilamentCashierPlugin;
use AIArmada\FilamentCashierChip\FilamentCashierChipPlugin;
use AIArmada\FilamentChip\FilamentChipPlugin;
use AIArmada\FilamentCustomers\FilamentCustomersPlugin;
use AIArmada\FilamentDocs\FilamentDocsPlugin;
use AIArmada\FilamentInventory\FilamentInventoryPlugin;
use AIArmada\FilamentJnt\FilamentJntPlugin;
use AIArmada\FilamentOrders\FilamentOrdersPlugin;
use AIArmada\FilamentPricing\FilamentPricingPlugin;
use AIArmada\FilamentProducts\FilamentProductsPlugin;
use AIArmada\FilamentShipping\FilamentShippingPlugin;
use AIArmada\FilamentTax\FilamentTaxPlugin;
use AIArmada\FilamentVouchers\FilamentVouchersPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->renderHook(PanelsRenderHook::HEAD_START, static fn (): HtmlString => new HtmlString(<<<'HTML'
                <script>
                    (function () {
                        function patchLivewireInterceptMessage() {
                            if (! window.Livewire?.interceptMessage) {
                                return;
                            }

                            if (window.Livewire.__filamentInterceptMessageShimApplied) {
                                return;
                            }

                            var original = window.Livewire.interceptMessage;

                            window.Livewire.interceptMessage = function (callback) {
                                return original(function (params) {
                                    if (params && params.message && ! params.component) {
                                        params.component = params.message.component;
                                    }

                                    return callback(params);
                                });
                            };

                            window.Livewire.__filamentInterceptMessageShimApplied = true;
                        }

                        document.addEventListener('livewire:init', patchLivewireInterceptMessage);
                        patchLivewireInterceptMessage();
                    })();
                </script>
                HTML))
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
                    ->label('Catalog'),
                NavigationGroup::make()
                    ->label('Pricing'),
                NavigationGroup::make()
                    ->label('Orders'),
                NavigationGroup::make()
                    ->label('Customers'),
                NavigationGroup::make()
                    ->label('Inventory'),
                NavigationGroup::make()
                    ->label('Marketing'),
                NavigationGroup::make()
                    ->label('Payments'),
                NavigationGroup::make()
                    ->label('Shipping'),
                NavigationGroup::make()
                    ->label('Tax'),
                NavigationGroup::make()
                    ->label('Docs'),
                NavigationGroup::make()
                    ->label('Settings')
                    ->collapsed(),
            ])
            ->pages([
                Dashboard::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
            ])
            ->plugins([
                FilamentProductsPlugin::make(),
                FilamentPricingPlugin::make(),
                FilamentOrdersPlugin::make(),
                FilamentCustomersPlugin::make(),
                FilamentCartPlugin::make(),
                FilamentVouchersPlugin::make(),
                FilamentInventoryPlugin::make(),
                FilamentAffiliatesPlugin::make(),
                FilamentChipPlugin::make(),
                FilamentCashierPlugin::make(),
                FilamentCashierChipPlugin::make(),
                FilamentJntPlugin::make(),
                FilamentShippingPlugin::make(),
                FilamentTaxPlugin::make(),
                FilamentDocsPlugin::make(),
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
