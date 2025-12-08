<?php

declare(strict_types=1);

namespace AIArmada\Cart;

use AIArmada\Cart\Events\Store\CartEventRecorder;
use AIArmada\Cart\Events\Store\CartEventRepositoryInterface;
use AIArmada\Cart\Events\Store\EloquentCartEventRepository;
use AIArmada\Cart\Http\Middleware\ThrottleCartOperations;
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Security\CartRateLimiter;
use AIArmada\Cart\Services\CartConditionResolver;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Services\TaxCalculator;
use AIArmada\Cart\Storage\CacheStorage;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\SessionStorage;
use AIArmada\Cart\Storage\StorageInterface;
use AIArmada\CommerceSupport\Contracts\NullOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
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
            ->runsMigrations()
            ->hasCommands([
                Console\Commands\ClearAbandonedCartsCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(CartConditionResolver::class);
        $this->app->alias(CartConditionResolver::class, 'cart.condition_resolver');

        $this->registerStorageDrivers();
        $this->registerCartManager();
        $this->registerMigrationService();
        $this->registerTaxCalculator();
        $this->registerRateLimiter();
        $this->registerEventStore();
    }

    public function bootingPackage(): void
    {
        $this->validateConfiguration('cart', [
            'storage',
            'money.default_currency',
        ]);

        $this->validateOwnerConfiguration();
        $this->registerEventListeners();
    }

    /**
     * Get the services provided by the provider.
     *
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
            TaxCalculator::class,
            CartRateLimiter::class,
            ThrottleCartOperations::class,
            CartEventRepositoryInterface::class,
            CartEventRecorder::class,
            'cart.condition_resolver',
            'cart.storage.session',
            'cart.storage.cache',
            'cart.storage.database',
            'cart.tax',
            'cart.rate_limiter',
            'cart.event_recorder',
        ];
    }

    /**
     * Validate owner configuration (fail-fast pattern)
     *
     * @throws RuntimeException If owner is enabled but resolver is not configured
     */
    protected function validateOwnerConfiguration(): void
    {
        if (! config('cart.owner.enabled', false)) {
            return;
        }

        $resolverClass = config('cart.owner.resolver', NullOwnerResolver::class);

        if (empty($resolverClass)) {
            throw new RuntimeException(
                'Cart owner is enabled but no resolver is configured. '.
                'Set CART_OWNER_RESOLVER or cart.owner.resolver to a class implementing OwnerResolverInterface.'
            );
        }

        if (! class_exists($resolverClass)) {
            throw new RuntimeException(
                "Cart owner resolver class '{$resolverClass}' does not exist."
            );
        }

        if (! is_subclass_of($resolverClass, OwnerResolverInterface::class) && $resolverClass !== NullOwnerResolver::class) {
            throw new RuntimeException(
                "Cart owner resolver '{$resolverClass}' must implement ".OwnerResolverInterface::class
            );
        }

        // Register the resolver in the container (only if not already bound)
        if (! $this->app->bound(OwnerResolverInterface::class)) {
            $this->app->singleton(OwnerResolverInterface::class, $resolverClass);
        }
    }

    /**
     * Register storage drivers
     */
    protected function registerStorageDrivers(): void
    {
        $this->app->bind('cart.storage.session', function (\Illuminate\Contracts\Foundation\Application $app) {
            $storage = new SessionStorage(
                $app->make(\Illuminate\Contracts\Session\Session::class),
                config('cart.session.key', 'cart')
            );

            return $this->applyOwnerScope($app, $storage);
        });

        $this->app->bind('cart.storage.cache', function (\Illuminate\Contracts\Foundation\Application $app) {
            $cacheStore = config('cart.cache.store', 'redis');
            $cacheRepository = $app->make(\Illuminate\Cache\CacheManager::class)->store($cacheStore);

            $storage = new CacheStorage(
                $cacheRepository, // @phpstan-ignore argument.type
                config('cart.cache.prefix', 'cart'),
                config('cart.cache.ttl', 86400)
            );

            return $this->applyOwnerScope($app, $storage);
        });

        $this->app->bind('cart.storage.database', function (\Illuminate\Contracts\Foundation\Application $app) {
            $connection = $app->make(\Illuminate\Database\ConnectionResolverInterface::class)->connection();

            $storage = new DatabaseStorage(
                $connection,
                config('cart.database.table', 'carts'),
                config('cart.database.ttl'),
            );

            return $this->applyOwnerScope($app, $storage);
        });

        // Bind StorageInterface to the configured storage driver
        $this->app->bind(StorageInterface::class, function (\Illuminate\Contracts\Foundation\Application $app): StorageInterface {
            $driver = config('cart.storage', 'session');

            if ($driver === 'cache' && ! config('cart.cache.enabled', false)) {
                throw new RuntimeException('Cache storage selected but cart.cache.enabled is false. Enable cache or choose another driver.');
            }

            return $app->make(sprintf('cart.storage.%s', $driver));
        });
    }

    /**
     * Apply owner scope to storage driver if owner is enabled
     */
    protected function applyOwnerScope(\Illuminate\Contracts\Foundation\Application $app, StorageInterface $storage): StorageInterface
    {
        if (! config('cart.owner.enabled', false)) {
            return $storage;
        }

        if (! $app->bound(OwnerResolverInterface::class)) {
            return $storage;
        }

        $resolver = $app->make(OwnerResolverInterface::class);
        $owner = $resolver->resolve();

        if ($owner === null) {
            return $storage;
        }

        return $storage->withOwner($owner);
    }

    /**
     * Register cart manager
     */
    protected function registerCartManager(): void
    {
        $this->app->singleton('cart', function (\Illuminate\Contracts\Foundation\Application $app) {
            $driver = config('cart.storage', 'session');
            $storage = $app->make(sprintf('cart.storage.%s', $driver));

            return new CartManager(
                storage: $storage,
                events: $app->make(Dispatcher::class),
                eventsEnabled: config('cart.events', true),
                conditionResolver: $app->make(CartConditionResolver::class)
            );
        });

        $this->app->alias('cart', CartManager::class);
        $this->app->alias('cart', Contracts\CartManagerInterface::class);
    }

    /**
     * Register cart migration service
     */
    protected function registerMigrationService(): void
    {
        $this->app->singleton(CartMigrationService::class, function (\Illuminate\Contracts\Foundation\Application $app): CartMigrationService {
            return new CartMigrationService;
        });
    }

    /**
     * Register event listeners for cart migration
     */
    protected function registerEventListeners(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);

        // Note: We removed DispatchCartUpdated subscriber as CartUpdated event is no longer used.
        // Applications should listen to specific events (ItemAdded, ConditionAdded, etc.) instead.

        if (config('cart.migration.auto_migrate_on_login', true)) {
            // Register login attempt listener to capture session ID before regeneration
            $dispatcher->listen(Attempting::class, HandleUserLoginAttempt::class);
            // Register login listener to handle cart migration
            $dispatcher->listen(Login::class, HandleUserLogin::class);
        }
    }

    /**
     * Register tax calculator service
     */
    protected function registerTaxCalculator(): void
    {
        $this->app->singleton(TaxCalculator::class, function (\Illuminate\Contracts\Foundation\Application $app) {
            return new TaxCalculator(
                defaultRate: config('cart.tax.default_rate', 0.0),
                defaultRegion: config('cart.tax.default_region'),
                pricesIncludeTax: config('cart.tax.prices_include_tax', false),
            );
        });

        $this->app->alias(TaxCalculator::class, 'cart.tax');
    }

    /**
     * Register rate limiter service
     */
    protected function registerRateLimiter(): void
    {
        $this->app->singleton(CartRateLimiter::class, function (\Illuminate\Contracts\Foundation\Application $app) {
            $limits = config('cart.rate_limiting.limits');
            $enabled = config('cart.rate_limiting.enabled', true);

            return new CartRateLimiter($limits, 'cart', $enabled);
        });

        $this->app->alias(CartRateLimiter::class, 'cart.rate_limiter');

        // Register middleware
        $this->app->singleton(ThrottleCartOperations::class, function (\Illuminate\Contracts\Foundation\Application $app) {
            return new ThrottleCartOperations(
                $app->make(CartRateLimiter::class)
            );
        });
    }

    /**
     * Register event store for event sourcing
     */
    protected function registerEventStore(): void
    {
        // Register repository interface
        $this->app->singleton(CartEventRepositoryInterface::class, EloquentCartEventRepository::class);

        // Register event recorder
        $this->app->singleton(CartEventRecorder::class, function (\Illuminate\Contracts\Foundation\Application $app) {
            $recorder = new CartEventRecorder(
                $app->make(CartEventRepositoryInterface::class)
            );

            // Disable recording if event sourcing is not enabled
            if (! config('cart.event_sourcing.enabled', false)) {
                $recorder->disable();
            }

            return $recorder;
        });

        $this->app->alias(CartEventRecorder::class, 'cart.event_recorder');
    }
}
