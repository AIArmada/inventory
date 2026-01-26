<?php

declare(strict_types=1);

namespace AIArmada\Cashier;

use AIArmada\Cashier\Support\CartIntegrationRegistrar;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for the unified multi-gateway Cashier package.
 *
 * This package provides a unified interface for multiple payment gateways.
 * It does NOT create its own tables - subscriptions are stored in the
 * respective gateway package's tables (subscriptions for Stripe,
 * chip_subscriptions for CHIP).
 */
final class CashierServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cashier')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(GatewayManager::class, function ($app) {
            return new GatewayManager($app);
        });

        $this->app->alias(GatewayManager::class, 'cashier');

        // Register cart integration
        $this->app->singleton(CartIntegrationRegistrar::class);
    }

    public function bootingPackage(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();

        // Register cart integration if cart package is installed
        $this->app->make(CartIntegrationRegistrar::class)->register();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            GatewayManager::class,
            CartIntegrationRegistrar::class,
            'cashier',
        ];
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole() || $this->app->runningUnitTests()) {
            $this->publishes([
                __DIR__ . '/../config/cashier.php' => $this->app->configPath('cashier.php'),
            ], 'cashier-config');

            if (class_exists(\Laravel\Cashier\CashierServiceProvider::class)) {
                $migrationsPath = base_path('vendor/laravel/cashier/database/migrations');

                if (is_dir($migrationsPath)) {
                    $this->publishes([
                        $migrationsPath => $this->app->databasePath('migrations'),
                    ], 'cashier-stripe-migrations');
                }
            }
        }
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if (Cashier::$registersRoutes) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }
}
