<?php

declare(strict_types=1);

namespace AIArmada\Affiliates;

use AIArmada\Affiliates\Cart\AffiliateDiscountConditionProvider;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use AIArmada\Affiliates\Services\AffiliateRegistrationService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Services\AttributionModel;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\Services\CommissionMaturityService;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\DailyAggregationService;
use AIArmada\Affiliates\Services\FraudDetectionService;
use AIArmada\Affiliates\Services\NetworkService;
use AIArmada\Affiliates\Services\PayoutReconciliationService;
use AIArmada\Affiliates\Services\Payouts\PayoutProcessorFactory;
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\Services\RankQualificationService;
use AIArmada\Affiliates\Support\Integrations\CartIntegrationRegistrar;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Support\Middleware\TrackAffiliateCookie;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class AffiliatesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('affiliates')
            ->hasConfigFile('affiliates')
            ->discoversMigrations()
            ->runsMigrations()
            ->hasRoutes(['api'])
            ->hasCommands([
                Console\Commands\ExportAffiliatePayoutCommand::class,
                Console\Commands\AggregateDailyStatsCommand::class,
                Console\Commands\ProcessRankUpgradesCommand::class,
                Console\Commands\ProcessScheduledPayoutsCommand::class,
                Console\Commands\ProcessCommissionMaturityCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CommissionCalculator::class);
        $this->app->singleton(AffiliateService::class);
        $this->app->singleton(AffiliatePayoutService::class);
        $this->app->singleton(AffiliateRegistrationService::class);
        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(AttributionModel::class);
        $this->app->singleton(NetworkService::class);
        $this->app->singleton(RankQualificationService::class);
        $this->app->singleton(DailyAggregationService::class);
        $this->app->singleton(FraudDetectionService::class);
        $this->app->singleton(PayoutProcessorFactory::class);
        $this->app->singleton(CommissionRuleEngine::class);
        $this->app->singleton(ProgramService::class);
        $this->app->singleton(CommissionMaturityService::class);
        $this->app->singleton(PayoutReconciliationService::class);

        $this->app->singleton(CartIntegrationRegistrar::class);
        $this->app->singleton(VoucherIntegrationRegistrar::class);
        $this->app->singleton(AffiliateDiscountConditionProvider::class);

        $this->app->alias(AffiliateService::class, 'affiliates');
    }

    public function packageBooted(): void
    {
        if (config('affiliates.cart.register_manager_proxy', true)) {
            app(CartIntegrationRegistrar::class)->register();
        }

        app(VoucherIntegrationRegistrar::class)->register();

        if (config('affiliates.cookies.enabled', true)) {
            $this->registerCookieTrackingMiddleware();
        }
    }

    /**
     * Register the affiliate cookie tracking middleware.
     */
    private function registerCookieTrackingMiddleware(): void
    {
        if (! $this->app->bound('router')) {
            return;
        }

        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('affiliates.cookie', TrackAffiliateCookie::class);

        if (config('affiliates.cookies.auto_register_middleware', true)) {
            $router->pushMiddlewareToGroup('web', TrackAffiliateCookie::class);
        }
    }
}
