<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\FilamentAffiliates\Services\AffiliateStatsAggregator;
use AIArmada\FilamentAffiliates\Services\PayoutExportService;
use AIArmada\FilamentAffiliates\Support\Integrations\CartBridge;
use AIArmada\FilamentAffiliates\Support\Integrations\VoucherBridge;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentAffiliatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-affiliates')
            ->hasConfigFile('filament-affiliates')
            ->hasViews();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentAffiliatesPlugin::class);
        $this->app->singleton(AffiliateStatsAggregator::class);
        $this->app->singleton(CartBridge::class);
        $this->app->singleton(VoucherBridge::class);
        $this->app->singleton(PayoutExportService::class);
    }

    public function packageBooted(): void
    {
        Filament::serving(function (): void {
            if (config('filament-affiliates.integrations.filament_cart', true)) {
                app(CartBridge::class)->warm();
            }

            if (config('filament-affiliates.integrations.filament_vouchers', true)) {
                app(VoucherBridge::class)->warm();
            }
        });

        Gate::policy(AffiliatePayout::class, Policies\AffiliatePayoutPolicy::class);
        Gate::policy(AffiliateFraudSignal::class, Policies\AffiliateFraudSignalPolicy::class);
    }
}
