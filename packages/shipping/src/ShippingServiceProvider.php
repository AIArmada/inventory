<?php

declare(strict_types=1);

namespace AIArmada\Shipping;

use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShippingZone;
use AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;
use AIArmada\Shipping\Policies\ShipmentPolicy;
use AIArmada\Shipping\Policies\ShippingZonePolicy;
use AIArmada\Shipping\Services\FreeShippingEvaluator;
use AIArmada\Shipping\Services\RateShoppingEngine;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/shipping.php',
            'shipping'
        );

        $this->app->singleton(ShippingManager::class, function ($app) {
            return new ShippingManager($app);
        });

        $this->app->alias(ShippingManager::class, 'shipping');

        $this->app->singleton(RateShoppingEngine::class, function ($app): RateShoppingEngine {
            /** @var array<string, mixed> $config */
            $config = (array) $app->make('config')->get('shipping.rate_shopping', []);

            return new RateShoppingEngine($app->make(ShippingManager::class), $config);
        });

        $this->app->singleton(FreeShippingEvaluator::class, function ($app): FreeShippingEvaluator {
            /** @var array<string, mixed> $config */
            $config = (array) $app->make('config')->get('shipping.free_shipping', []);
            $config['currency'] ??= (string) $app->make('config')->get('shipping.defaults.currency', 'MYR');

            return new FreeShippingEvaluator($config);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/shipping.php' => config_path('shipping.php'),
            ], 'shipping-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'shipping-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->registerPolicies();
        $this->registerEventListeners();
        $this->registerCommands();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(ShippingZone::class, ShippingZonePolicy::class);
        Gate::policy(ReturnAuthorization::class, ReturnAuthorizationPolicy::class);
    }

    protected function registerEventListeners(): void
    {
        // Event listeners will be registered here
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Commands will be registered here
            ]);
        }
    }
}
