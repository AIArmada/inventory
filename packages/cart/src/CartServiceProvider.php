<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\ValidatesConfiguration;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Events\Dispatcher;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CartServiceProvider extends PackageServiceProvider
{
    use ValidatesConfiguration;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('cart')
            ->hasConfigFile()
            ->discoversMigrations()
            ->hasCommands([
                Console\Commands\ClearAbandonedCartsCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(CartConditionResolver::class);
        $this->app->alias(CartConditionResolver::class, 'cart.condition_resolver');

        $this->registerStorage();
        $this->registerCartManager();
        $this->registerMigrationService();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('cart', [
            'money.default_currency',
        ]);

        $this->validateOwnerConfiguration();
        $this->registerEventListeners();
    }

    /**
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'cart',
            Cart::class,
            StorageInterface::class,
            CartMigrationService::class,
            CartConditionResolver::class,
            'cart.condition_resolver',
            'cart.storage',
        ];
    }

    /**
     * @throws RuntimeException If owner is enabled but resolver is not configured
     */
    protected function validateOwnerConfiguration(): void
    {
        if (! config('cart.owner.enabled', false)) {
            return;
        }

        if (! $this->app->bound(OwnerResolverInterface::class)) {
            throw new RuntimeException(
                'Cart owner is enabled but no resolver is bound. ' .
                'Bind ' . OwnerResolverInterface::class . ' (recommended via COMMERCE_OWNER_RESOLVER / commerce-support config).'
            );
        }
    }

    protected function registerStorage(): void
    {
        $this->app->bind('cart.storage', function (\Illuminate\Contracts\Foundation\Application $app) {
            $connection = $app->make(\Illuminate\Database\ConnectionResolverInterface::class)->connection();

            $storage = new DatabaseStorage(
                $connection,
                config('cart.database.table', 'carts'),
                config('cart.database.ttl'),
            );

            if (config('cart.owner.enabled', false)) {
                $owner = OwnerContext::resolve();
                if ($owner !== null) {
                    return $storage->withOwner($owner);
                }
            }

            return $storage;
        });

        $this->app->bind(StorageInterface::class, fn ($app) => $app->make('cart.storage'));
    }

    protected function registerCartManager(): void
    {
        $this->app->singleton('cart', function (\Illuminate\Contracts\Foundation\Application $app) {
            return new CartManager(
                storage: $app->make('cart.storage'),
                events: $app->make(Dispatcher::class),
                eventsEnabled: config('cart.events', true),
                conditionResolver: $app->make(CartConditionResolver::class)
            );
        });

        $this->app->alias('cart', CartManager::class);
        $this->app->alias('cart', Contracts\CartManagerInterface::class);
    }

    protected function registerMigrationService(): void
    {
        $this->app->singleton(CartMigrationService::class, fn () => new CartMigrationService);
    }

    protected function registerEventListeners(): void
    {
        if (! config('cart.migration.auto_migrate_on_login', true)) {
            return;
        }

        $dispatcher = $this->app->make(Dispatcher::class);
        $dispatcher->listen(Attempting::class, HandleUserLoginAttempt::class);
        $dispatcher->listen(Login::class, HandleUserLogin::class);
    }
}
