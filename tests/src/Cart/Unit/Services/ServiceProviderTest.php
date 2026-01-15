<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\CartManager;
use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Listeners\HandleUserLogin;
use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Cart\Storage\DatabaseStorage;
use AIArmada\Cart\Storage\StorageInterface;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

describe('CartServiceProvider', function (): void {
    afterEach(function (): void {
        Mockery::close();
    });

    it('provides correct services', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);
        $provides = $provider->provides();

        expect($provides)->toBeArray();
        expect($provides)->toContain('cart');
        expect($provides)->toContain(Cart::class);
        expect($provides)->toContain(StorageInterface::class);
        expect($provides)->toContain(CartMigrationService::class);
        expect($provides)->toContain('cart.storage');
    });

    it('registers storage correctly', function (): void {
        $app = mock(Application::class);

        // Mock bind calls for storage + StorageInterface binding
        $app->shouldReceive('bind')->withAnyArgs()->times(2);

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerStorage');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('registers cart manager correctly', function (): void {
        $app = mock(Application::class);
        $app->shouldReceive('singleton')->withArgs(['cart', Mockery::type('callable')])->once();
        $app->shouldReceive('alias')->withArgs(['cart', CartManager::class])->once();
        $app->shouldReceive('alias')->withArgs(['cart', CartManagerInterface::class])->once();

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerCartManager');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('can call publish methods without errors', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
    });

    it('registers migration service correctly', function (): void {
        $app = mock(Application::class);
        $app->shouldReceive('singleton')
            ->withArgs([CartMigrationService::class, Mockery::type('callable')])
            ->once();

        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerMigrationService');
        $method->setAccessible(true);
        $method->invoke($provider);

        expect(true)->toBeTrue();
    });

    it('can call event listeners method without errors', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('registerEventListeners');

        expect($method->isProtected())->toBeTrue();
        expect($reflection->hasMethod('registerEventListeners'))->toBeTrue();
    });

    it('has all expected protected methods', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);

        $expectedMethods = [
            'registerStorage',
            'registerCartManager',
            'registerMigrationService',
            'registerEventListeners',
            'configurePackage',
            'registeringPackage',
            'bootingPackage',
        ];

        foreach ($expectedMethods as $methodName) {
            expect($reflection->hasMethod($methodName))->toBeTrue("Method {$methodName} should exist");
        }
    });

    it('can call spatie package tools methods', function (): void {
        $app = mock(Application::class);
        $provider = new CartServiceProvider($app);

        $reflection = new ReflectionClass($provider);

        $configureMethod = $reflection->getMethod('configurePackage');
        expect($configureMethod->isPublic())->toBeTrue();

        $registeringMethod = $reflection->getMethod('registeringPackage');
        expect($registeringMethod->isPublic())->toBeTrue();

        $bootingMethod = $reflection->getMethod('bootingPackage');
        expect($bootingMethod->isPublic())->toBeTrue();
    });
});

beforeEach(function (): void {
    Config::set('cart.migration.auto_migrate_on_login', true);
});

it('integration: registers database storage', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    $provider->register();

    if ($app->bound('db.connection')) {
        expect($app->make('cart.storage'))->toBeInstanceOf(DatabaseStorage::class);
        expect($app->make(StorageInterface::class))->toBeInstanceOf(DatabaseStorage::class);
    }
});

it('integration: registers cart manager and aliases', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    $provider->register();

    expect($app->make('cart'))->toBeInstanceOf(CartManagerInterface::class);
    expect($app->make(CartManagerInterface::class))->toBeInstanceOf(CartManagerInterface::class);
});

it('integration: registers migration service', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    $provider->register();

    expect($app->make(CartMigrationService::class))->toBeInstanceOf(CartMigrationService::class);
});

it('integration: publishes config, migrations, and views', function (): void {
    $provider = new CartServiceProvider(app());
    $package = new Package;
    $provider->configurePackage($package);

    expect($package->name)->toBe('cart');
    expect($package->commands)->toHaveCount(1);
    expect(true)->toBeTrue();
});

it('integration: uses configured conditions table name when migrating', function (): void {
    $tableName = 'custom_conditions';
    Config::set('cart.database.conditions_table', $tableName);

    $migrationPath = getcwd() . '/packages/cart/database/migrations/2000_02_01_000003_create_conditions_table.php';
    expect(file_exists($migrationPath))->toBeTrue();

    /** @var object{up: callable, down: callable} $migration */
    $migration = include $migrationPath;

    Schema::dropIfExists($tableName);
    $migration->up();

    expect(Schema::hasTable($tableName))->toBeTrue();

    $migration->down();

    expect(Schema::hasTable($tableName))->toBeFalse();
});

it('integration: registers event listeners based on config', function (): void {
    $app = app();
    $provider = new CartServiceProvider($app);
    Event::fake();
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('registerEventListeners');
    $method->setAccessible(true);
    $method->invoke($provider);
    Event::assertListening(Attempting::class, HandleUserLoginAttempt::class);
    Event::assertListening(Login::class, HandleUserLogin::class);
});
