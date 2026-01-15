<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers;

use AIArmada\FilamentVouchers\Services\VoucherStatsAggregator;
use AIArmada\FilamentVouchers\Support\Integrations\FilamentCartBridge;
use AIArmada\FilamentVouchers\Support\OwnerTypeRegistry;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentVouchersServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-vouchers';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('filament-vouchers')
            ->hasViews('filament-vouchers');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentVouchersPlugin::class);
        $this->app->singleton(VoucherStatsAggregator::class);
        $this->app->singleton(OwnerTypeRegistry::class);
        $this->app->singleton(FilamentCartBridge::class);
    }

    public function packageBooted(): void
    {
        // Register Livewire components manually for class-based widgets
        $this->registerLivewireComponents();

        Filament::registerRenderHook('panels::body.start', static function (): void {
            // Registering the plugin implicitly ensures it is discoverable via Filament's panel registry.
        });

        Filament::serving(static function (): void {
            // The bridge lazily inspects whether Filament Cart is present. Resolving the singleton ensures
            // any integration hooks are prepared before Filament renders resources.
            app(FilamentCartBridge::class)->warm();
        });
    }

    protected function registerLivewireComponents(): void
    {
        // Register all widget components with Livewire
        // Class-based components require manual registration
        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.voucher-usage-timeline-widget',
            Widgets\VoucherUsageTimelineWidget::class
        );

        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.voucher-cart-stats-widget',
            Widgets\VoucherCartStatsWidget::class
        );

        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.applied-voucher-badges-widget',
            Widgets\AppliedVoucherBadgesWidget::class
        );

        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.quick-apply-voucher-widget',
            Widgets\QuickApplyVoucherWidget::class
        );

        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.voucher-suggestions-widget',
            Widgets\VoucherSuggestionsWidget::class
        );

        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.voucher-wallet-stats-widget',
            Widgets\VoucherWalletStatsWidget::class
        );

        Livewire::component(
            'a-i-armada.filament-vouchers.widgets.redemption-trend-chart',
            Widgets\RedemptionTrendChart::class
        );
    }
}
