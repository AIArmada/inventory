<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentCashierChip;

use AIArmada\CashierChip\Cashier;
use AIArmada\CashierChip\CashierChipServiceProvider;
use AIArmada\CashierChip\Subscription;
use AIArmada\CashierChip\SubscriptionItem;
use AIArmada\CashierChip\Testing\FakeChipCollectService;
use AIArmada\Chip\ChipServiceProvider;
use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\TestPanelProvider;
use AIArmada\Commerce\Tests\FilamentCashierChip\Fixtures\User;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentCashierChip\FilamentCashierChipServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use DateInterval;
use Filament\FilamentServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected ?FakeChipCollectService $fakeChip = null;

    protected function setUp(): void
    {
        parent::setUp();

        Cashier::useCustomerModel(User::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        $this->fakeChip = Cashier::fake();
    }

    protected function tearDown(): void
    {
        if ($this->fakeChip !== null) {
            Cashier::unfake();
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            SupportServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            ChipServiceProvider::class,
            CashierChipServiceProvider::class,
            PermissionServiceProvider::class,
            FilamentCashierChipServiceProvider::class,
            TestPanelProvider::class,
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

        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        $app['config']->set('permission.models.permission', Permission::class);
        $app['config']->set('permission.models.role', Role::class);
        $app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        $app['config']->set('permission.column_names', [
            'role_pivot_key' => 'role_id',
            'permission_pivot_key' => 'permission_id',
            'model_morph_key' => 'model_id',
            'team_foreign_key' => 'team_id',
        ]);
        $app['config']->set('permission.cache', [
            'key' => 'spatie.permission.cache',
            'store' => 'array',
            'expiration_time' => DateInterval::createFromDateString('24 hours'),
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

        // Configure filament-cashier-chip settings
        $app['config']->set('filament-cashier-chip.navigation.group', 'Billing');
        $app['config']->set('filament-cashier-chip.navigation.badge_color', 'success');
        $app['config']->set('filament-cashier-chip.tables.polling_interval', '45s');
        $app['config']->set('filament-cashier-chip.tables.amount_precision', 2);

        $app['config']->set('filament-cashier-chip.resources.navigation_sort.subscriptions', 10);
        $app['config']->set('filament-cashier-chip.resources.navigation_sort.customers', 20);
        $app['config']->set('filament-cashier-chip.resources.navigation_sort.invoices', 30);

        $app['config']->set('filament-cashier-chip.features.subscriptions', true);
        $app['config']->set('filament-cashier-chip.features.customers', true);
        $app['config']->set('filament-cashier-chip.features.invoices', true);
        $app['config']->set('filament-cashier-chip.features.dashboard_widgets', true);

        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.mrr', true);
        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.active_subscribers', true);
        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.churn_rate', true);
        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.attention_required', true);
        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.revenue_chart', true);
        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.subscription_distribution', true);
        $app['config']->set('filament-cashier-chip.features.dashboard.widgets.trial_conversions', true);
    }

    protected function defineDatabaseMigrations(): void
    {
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

        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/cashier-chip/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../packages/chip/database/migrations');
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], $attributes));
    }
}
