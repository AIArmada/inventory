<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\CashierChip;

use AIArmada\CashierChip\CashierChip;
use AIArmada\CashierChip\CashierChipServiceProvider;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Commerce\Tests\CashierChip\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

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

        $this->setUpDatabase();

        CashierChip::useCustomerModel(User::class);
        CashierChip::useSubscriptionModel(Subscription::class);
        CashierChip::useSubscriptionItemModel(SubscriptionItem::class);

        // Enable fake CHIP service for all tests
        $this->fakeChip = CashierChip::fake();
    }

    protected function tearDown(): void
    {
        // Reset and disable fake after each test
        CashierChip::unfake();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Illuminate\Events\EventServiceProvider::class,
            \Illuminate\Session\SessionServiceProvider::class,
            \Illuminate\Cache\CacheServiceProvider::class,
            \Illuminate\Database\DatabaseServiceProvider::class,
            \AIArmada\Chip\ChipServiceProvider::class,
            CashierChipServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
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

    protected function setUpDatabase(): void
    {
        // Users table
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('chip_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->string('default_pm_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        // Subscriptions table
        Schema::create('chip_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id');
            $table->string('type');
            $table->string('chip_id')->unique();
            $table->string('chip_status');
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('recurring_token')->nullable();
            $table->string('billing_interval')->default('month');
            $table->integer('billing_interval_count')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'chip_status']);
        });

        // Subscription items table
        Schema::create('chip_subscription_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id');
            $table->string('chip_id')->unique();
            $table->string('chip_product')->nullable();
            $table->string('chip_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('unit_amount')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'chip_price']);
        });
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }
}
