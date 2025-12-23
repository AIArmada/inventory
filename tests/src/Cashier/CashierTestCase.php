<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier;

use AIArmada\Cashier\Cashier;
use AIArmada\Cashier\CashierServiceProvider;
use AIArmada\Cashier\GatewayManager;
use AIArmada\Commerce\Tests\Cashier\Fixtures\User;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for Cashier package tests.
 *
 * Note: This package is a wrapper/adapter layer that delegates to underlying
 * gateway packages (laravel/cashier for Stripe, aiarmada/cashier-chip for CHIP).
 * Tests here focus on the wrapper functionality, not the underlying models.
 */
abstract class CashierTestCase extends Orchestra
{
    protected ?GatewayManager $gatewayManager = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();

        Cashier::useCustomerModel(User::class);

        if ($this->app->bound(GatewayManager::class)) {
            $this->gatewayManager = $this->app->make(GatewayManager::class);
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            EventServiceProvider::class,
            SessionServiceProvider::class,
            CacheServiceProvider::class,
            DatabaseServiceProvider::class,
            CashierServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure session
        $app['config']->set('session.driver', 'array');

        // Configure cache
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Configure Cashier settings for testing
        $app['config']->set('cashier.default', 'stripe');
        $app['config']->set('cashier.currency', 'USD');
        $app['config']->set('cashier.locale', 'en_US');

        // Configure gateway settings
        $app['config']->set('cashier.gateways', [
            'stripe' => [
                'driver' => 'stripe',
                'secret' => 'sk_test_xxx',
                'webhook_secret' => 'whsec_xxx',
                'currency' => 'USD',
                'currency_locale' => 'en_US',
            ],
            'chip' => [
                'driver' => 'chip',
                'brand_id' => 'test_brand_id',
                'currency' => 'MYR',
                'currency_locale' => 'ms_MY',
            ],
        ]);

        // Configure customer model
        $app['config']->set('cashier.customer_model', User::class);
    }

    protected function setUpDatabase(): void
    {
        // Users table - for testing billable functionality
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('stripe_id')->nullable()->index();
            $table->string('chip_id')->nullable()->index();
            $table->string('testable_id')->nullable()->index();
            $table->string('preferred_gateway')->nullable();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        // Note: Subscription tables are NOT created here.
        // In production, they come from laravel/cashier (subscriptions)
        // and aiarmada/cashier-chip (chip_subscriptions).
        // For unit tests that need to test adapters/wrappers, mock the
        // underlying packages instead.
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }
}
