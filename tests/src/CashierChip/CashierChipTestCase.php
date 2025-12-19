<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\CashierChipServiceProvider;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\ChipServiceProvider;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

abstract class CashierChipTestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * The fake CHIP service.
     */
    protected FakeChipCollectService $fakeChip;

    protected function setUp(): void
    {
        parent::setUp();

        Cashier::useCustomerModel(User::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        // Manually require factories due to autoload issues in test environment
        if (file_exists(__DIR__ . '/../../../../packages/cashier-chip/database/factories/SubscriptionFactory.php')) {
            require_once __DIR__ . '/../../../../packages/cashier-chip/database/factories/SubscriptionFactory.php';
        }
        if (file_exists(__DIR__ . '/../../../../packages/cashier-chip/database/factories/SubscriptionItemFactory.php')) {
            require_once __DIR__ . '/../../../../packages/cashier-chip/database/factories/SubscriptionItemFactory.php';
        }

        // Enable fake CHIP service for all tests
        $this->fakeChip = Cashier::fake();
    }

    protected function tearDown(): void
    {
        // Reset and disable fake after each test
        Cashier::unfake();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            EventServiceProvider::class,
            SessionServiceProvider::class,
            CacheServiceProvider::class,
            DatabaseServiceProvider::class,
            PermissionServiceProvider::class,
            ChipServiceProvider::class,
            CashierChipServiceProvider::class,
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

        // Configure CHIP settings for testing
        $app['config']->set('chip.collect.api_key', 'test_secret_key');
        $app['config']->set('chip.collect.secret_key', 'test_secret_key');
        $app['config']->set('chip.collect.brand_id', 'test_brand_id');
        $app['config']->set('chip.collect.environment', 'sandbox');
        $app['config']->set('chip.is_sandbox', true);

        // Configure Cashier CHIP settings
        $app['config']->set('cashier-chip.currency', 'MYR');
        $app['config']->set('cashier-chip.currency_locale', 'ms_MY');
        $app['config']->set('cashier-chip.webhooks.secret', 'test_webhook_secret');
        $app['config']->set('cashier-chip.webhooks.verify_signature', false);
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        // Create users table first (before cashier-chip migrations run)
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('chip_id')->nullable()->index();
            $table->string('chip_default_payment_method')->nullable();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->string('default_pm_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        // Load package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/cashier-chip/database/migrations');
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }
}
