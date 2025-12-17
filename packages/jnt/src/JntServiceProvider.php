<?php

declare(strict_types=1);

namespace AIArmada\Jnt;

use AIArmada\Jnt\Cart\JntShippingCalculator;
use AIArmada\Jnt\Cart\JntShippingConditionProvider;
use AIArmada\Jnt\Console\Commands\ConfigCheckCommand;
use AIArmada\Jnt\Console\Commands\HealthCheckCommand;
use AIArmada\Jnt\Console\Commands\OrderCancelCommand;
use AIArmada\Jnt\Console\Commands\OrderCreateCommand;
use AIArmada\Jnt\Console\Commands\OrderPrintCommand;
use AIArmada\Jnt\Console\Commands\OrderTrackCommand;
use AIArmada\Jnt\Console\Commands\WebhookTestCommand;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Http\Middleware\VerifyWebhookSignature;
use AIArmada\Jnt\Listeners\SendShipmentNotifications;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use AIArmada\Jnt\Services\WebhookService;
use AIArmada\Jnt\Shipping\JntShippingDriver;
use AIArmada\Jnt\Support\Integrations\CartIntegrationRegistrar;
use AIArmada\Shipping\ShippingManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * J&T Express Service Provider
 *
 * Bootstraps the J&T Express package for Laravel integration using Spatie's package tools.
 * Handles service registration, configuration publishing, command registration,
 * webhook setup, and configuration validation.
 */
class JntServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('jnt')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasRoute('webhooks')
            ->hasCommands([
                ConfigCheckCommand::class,
                HealthCheckCommand::class,
                OrderCreateCommand::class,
                OrderTrackCommand::class,
                OrderCancelCommand::class,
                OrderPrintCommand::class,
                WebhookTestCommand::class,
            ]);
    }

    /**
     * Register package services.
     */
    public function registeringPackage(): void
    {
        $this->registerServices();
        $this->registerOptionalIntegrations();
    }

    /**
     * Bootstrap package services.
     */
    public function bootingPackage(): void
    {
        $this->registerMiddleware();
        $this->registerCartIntegration();
        $this->registerShippingDriver();
        $this->registerEventListeners();
    }

    /**
     * Register package services in the container.
     */
    protected function registerServices(): void
    {
        // Register main J&T Express service
        $this->app->singleton(JntExpressService::class, function (Application $app): JntExpressService {
            $config = $app['config']['jnt'];

            return new JntExpressService(
                customerCode: $config['customer_code'] ?? null,
                password: $config['password'] ?? null,
                config: $config,
            );
        });

        // Register facade accessor alias
        $this->app->alias(JntExpressService::class, 'jnt-express');

        // Register webhook service
        $this->app->singleton(WebhookService::class, fn (Application $app): WebhookService => new WebhookService(
            privateKey: $app['config']['jnt']['private_key'] ?? null,
        ));

        // Register status mapper service
        $this->app->singleton(JntStatusMapper::class, fn (): JntStatusMapper => new JntStatusMapper);
        $this->app->alias(JntStatusMapper::class, 'jnt.status-mapper');

        // Register tracking service
        $this->app->singleton(JntTrackingService::class, fn (Application $app): JntTrackingService => new JntTrackingService(
            expressService: $app->make(JntExpressService::class),
            statusMapper: $app->make(JntStatusMapper::class),
        ));
        $this->app->alias(JntTrackingService::class, 'jnt.tracking');
    }

    /**
     * Register Cart/Shipping optional integrations.
     */
    protected function registerOptionalIntegrations(): void
    {
        if (class_exists('AIArmada\\Cart\\CartManager')) {
            $this->app->singleton(
                JntShippingCalculator::class,
                fn (Application $app): JntShippingCalculator => new JntShippingCalculator(
                    $app->make(JntExpressService::class)
                )
            );

            $this->app->singleton(
                JntShippingConditionProvider::class,
                fn (Application $app): JntShippingConditionProvider => new JntShippingConditionProvider(
                    $app->make(JntExpressService::class),
                    $app->make(JntShippingCalculator::class)
                )
            );

            $this->app->singleton(CartIntegrationRegistrar::class);
        }

        if (class_exists(ShippingManager::class)) {
            $this->app->singleton(
                JntShippingDriver::class,
                fn (Application $app): JntShippingDriver => new JntShippingDriver(
                    $app->make(JntExpressService::class),
                    $app->make(JntTrackingService::class),
                    $app->make(JntStatusMapper::class),
                )
            );
        }
    }

    /**
     * Register package middleware.
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('jnt.verify.signature', VerifyWebhookSignature::class);
    }

    /**
     * Register cart integration if enabled.
     */
    protected function registerCartIntegration(): void
    {
        if (! class_exists('AIArmada\\Cart\\CartManager')) {
            return;
        }

        if (config('jnt.cart.register_manager_proxy', true)) {
            $this->app->make(CartIntegrationRegistrar::class)->register();
        }
    }

    /**
     * Register JNT as a shipping driver if shipping package is available.
     */
    protected function registerShippingDriver(): void
    {
        // Only register if the shipping package is installed
        if (! class_exists(ShippingManager::class)) {
            return;
        }

        /** @var ShippingManager $shippingManager */
        $shippingManager = $this->app->make(ShippingManager::class);

        $shippingManager->extend('jnt', fn (Application $app): JntShippingDriver => $app->make(JntShippingDriver::class));
    }

    /**
     * Register event listeners for status change notifications.
     */
    protected function registerEventListeners(): void
    {
        if (! config('jnt.notifications.enabled', true)) {
            return;
        }

        Event::listen(
            JntOrderStatusChanged::class,
            SendShipmentNotifications::class
        );
    }
}
