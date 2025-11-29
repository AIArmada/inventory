<?php

declare(strict_types=1);

namespace AIArmada\Affiliates;

use AIArmada\Affiliates\Contracts\AffiliateOwnerResolver;
use AIArmada\Affiliates\Services\AffiliatePayoutService;
use AIArmada\Affiliates\Services\AffiliateService;
use AIArmada\Affiliates\Services\AttributionModel;
use AIArmada\Affiliates\Services\CommissionCalculator;
use AIArmada\Affiliates\Support\Integrations\CartIntegrationRegistrar;
use AIArmada\Affiliates\Support\Integrations\VoucherIntegrationRegistrar;
use AIArmada\Affiliates\Support\Middleware\TrackAffiliateCookie;
use AIArmada\Affiliates\Support\Resolvers\NullOwnerResolver;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
use Illuminate\Routing\Router;
use InvalidArgumentException;
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
            ->hasRoute('api')
            ->hasCommands([
                Console\Commands\ExportAffiliatePayoutCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(CommissionCalculator::class);
        $this->app->singleton(AffiliateService::class);
        $this->app->singleton(AffiliatePayoutService::class);
        $this->app->singleton(WebhookDispatcher::class);
        $this->app->singleton(AttributionModel::class);

        $this->app->singleton(AffiliateOwnerResolver::class, function ($app): AffiliateOwnerResolver {
            $resolverClass = config('affiliates.owner.resolver', NullOwnerResolver::class);

            $resolver = $app->make($resolverClass);

            if (! $resolver instanceof AffiliateOwnerResolver) {
                throw new InvalidArgumentException(sprintf(
                    '%s must implement %s',
                    $resolverClass,
                    AffiliateOwnerResolver::class
                ));
            }

            return $resolver;
        });

        $this->app->singleton(CartIntegrationRegistrar::class);
        $this->app->singleton(VoucherIntegrationRegistrar::class);

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
     * @return array<int, string>
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
