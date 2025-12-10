<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests;

use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Cart\CartServiceProvider;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Chip\ChipServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider;
use AIArmada\Docs\DocsServiceProvider;
use AIArmada\Docs\Numbering\Strategies\DefaultNumberStrategy;
use AIArmada\FilamentAffiliates\FilamentAffiliatesServiceProvider;
use AIArmada\FilamentAuthz\FilamentAuthzServiceProvider;
use AIArmada\FilamentCart\FilamentCartServiceProvider;
use AIArmada\FilamentCashier\FilamentCashierServiceProvider;
use AIArmada\FilamentChip\FilamentChipServiceProvider;
use AIArmada\FilamentShipping\FilamentShippingServiceProvider;
use AIArmada\FilamentVouchers\FilamentVouchersServiceProvider;
use AIArmada\Jnt\JntServiceProvider;
use AIArmada\Shipping\Facades\Shipping;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\VoucherServiceProvider;
use BackedEnum;
use DateInterval;
use DateTimeInterface;
use Filament\FilamentServiceProvider;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Transformers\ArrayableTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Transformers\EnumTransformer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(function (string $modelName) {
            return Str::replace('Models', 'Database\\Factories', $modelName) . 'Factory';
        });

        // Start session for Livewire/Filament tests
        $this->app['session']->start();

        // Share an empty error bag so Blade always receives the expected variable
        $this->app['view']->share('errors', tap(new ViewErrorBag, static function (ViewErrorBag $bag): void {
            $bag->put('default', new MessageBag);
        }));

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            SupportServiceProvider::class,
            EventServiceProvider::class,
            SessionServiceProvider::class,
            ViewServiceProvider::class,
            HashServiceProvider::class,
            CacheServiceProvider::class,
            DatabaseServiceProvider::class,
            TranslationServiceProvider::class,
            ValidationServiceProvider::class,
            LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Tables\TablesServiceProvider::class,
            FilamentServiceProvider::class,
            CartServiceProvider::class,
            ChipServiceProvider::class,
            JntServiceProvider::class,
            DocsServiceProvider::class,
            VoucherServiceProvider::class,
            FilamentCartServiceProvider::class,
            FilamentChipServiceProvider::class,
            PermissionServiceProvider::class,
            FilamentAuthzServiceProvider::class,
            FilamentVouchersServiceProvider::class,
            AffiliatesServiceProvider::class,
            FilamentAffiliatesServiceProvider::class,
            \AIArmada\Shipping\ShippingServiceProvider::class,
            FilamentShippingServiceProvider::class,
            FilamentCashierServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Cart' => Cart::class,
            'Voucher' => Voucher::class,
            'Shipping' => Shipping::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup the test environment
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');
        $app['config']->set('database.default', 'testing');

        // Set USD currency for consistent test formatting
        $app['config']->set('cart.money.default_currency', 'USD');

        // Use in-memory SQLite for testing
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Configure session
        $app['config']->set('session.driver', 'array');
        $app['config']->set('session.lifetime', 120);
        $app['config']->set('session.expire_on_close', false);
        $app['config']->set('session.encrypt', false);
        $app['config']->set('session.files', storage_path('framework/sessions'));
        $app['config']->set('session.connection', null);
        $app['config']->set('session.table', 'sessions');
        $app['config']->set('session.store', null);
        $app['config']->set('session.lottery', [2, 100]);
        $app['config']->set('session.cookie', 'laravel_session');
        $app['config']->set('session.path', '/');
        $app['config']->set('session.domain', null);
        $app['config']->set('session.secure', false);
        $app['config']->set('session.http_only', true);
        $app['config']->set('session.same_site', 'lax');

        // Configure cache
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Configure cart cache to use array driver in tests (avoid Redis connection issues in CI)
        $app['config']->set('cart.cache.store', 'array');

        // Configure Spatie Laravel Data settings for testing
        $app['config']->set('data.date_format', DATE_ATOM);
        $app['config']->set('data.date_timezone', null);
        $app['config']->set('data.max_transformation_depth', 512);
        $app['config']->set('data.throw_when_max_transformation_depth_reached', true);
        $app['config']->set('data.features.cast_and_transform_iterables', true);
        $app['config']->set('data.transformers', [
            DateTimeInterface::class => DateTimeInterfaceTransformer::class,
            Arrayable::class => ArrayableTransformer::class,
            BackedEnum::class => EnumTransformer::class,
        ]);
        $app['config']->set('data.casts', [
            DateTimeInterface::class => DateTimeInterfaceCast::class,
            BackedEnum::class => EnumCast::class,
        ]);

        // Configure cart settings for testing
        $app['config']->set('cart.storage', 'database');
        $app['config']->set('cart.database.connection', 'testing');
        $app['config']->set('cart.database.table', 'carts');
        $app['config']->set('cart.events', true);

        // Configure docs settings for testing
        $app['config']->set('docs.database.table_prefix', '');
        $app['config']->set('docs.types', [
            'invoice' => [
                'default_template' => 'doc-default',
                'numbering' => [
                    'strategy' => DefaultNumberStrategy::class,
                    'prefix' => 'INV',
                ],
            ],
            'receipt' => [
                'default_template' => 'doc-default',
                'numbering' => [
                    'strategy' => DefaultNumberStrategy::class,
                    'prefix' => 'RCP',
                ],
            ],
            'credit_note' => [
                'default_template' => 'doc-default',
                'numbering' => [
                    'strategy' => DefaultNumberStrategy::class,
                    'prefix' => 'CN',
                ],
            ],
        ]);
        $app['config']->set('docs.numbering.format', [
            'date_format' => 'Ymd',
            'separator' => '-',
            'use_sequence' => true,
            'sequence_padding' => 5,
        ]);
        // Configure CHIP settings for testing
        $app['config']->set('chip.collect.api_key', 'test_secret_key');
        $app['config']->set('chip.collect.secret_key', 'test_secret_key'); // For backward compatibility with tests
        $app['config']->set('chip.collect.brand_id', 'test_brand_id');
        $app['config']->set('chip.collect.environment', 'sandbox');
        $app['config']->set('chip.send.api_key', 'test_api_key');
        $app['config']->set('chip.send.api_secret', 'test_send_secret');
        $app['config']->set('chip.webhooks.public_key', 'test_public_key');
        $app['config']->set('chip.is_sandbox', true);

        // Configure JNT settings for testing
        $app['config']->set('jnt.environment', 'testing');
        $app['config']->set('jnt.api_account', '640826271705595946'); // J&T official testing account
        $app['config']->set('jnt.private_key', '8e88c8477d4e4939859c560192fcafbc'); // J&T official testing key
        $app['config']->set('jnt.customer_code', 'test_customer_code');
        $app['config']->set('jnt.password', 'test_password');

        // Configure filament-chip settings for testing
        $app['config']->set('filament-chip.navigation_group', 'CHIP Operations');
        $app['config']->set('filament-chip.navigation_badge_color', 'primary');
        $app['config']->set('filament-chip.polling_interval', '45s');

        // Configure vouchers settings for testing
        $app['config']->set('vouchers.redemption', [
            'manual_requires_flag' => true,
            'manual_channel' => 'manual',
        ]);

        // Configure Spatie Permission settings for testing
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

        // Configure filament-authz settings for testing
        $app['config']->set('filament-authz.guards', ['web', 'admin']);
        $app['config']->set('filament-authz.super_admin_role', 'Super Admin');

        // Configure auth to use our test User model
        $app['config']->set('auth.providers.users.model', Fixtures\Models\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/chip/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/vouchers/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../vendor/spatie/laravel-permission/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/affiliates/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/docs/database/migrations');
    }

    protected function setUpDatabase(): void
    {
        // Users table for permission tests
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        // Spatie Permission tables
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            // Hierarchy columns for filament-authz
            $table->foreignUuid('parent_role_id')->nullable();
            $table->foreignUuid('template_id')->nullable();
            $table->text('description')->nullable();
            $table->integer('level')->default(0);
            $table->json('metadata')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_assignable')->default(true);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
            $table->index('parent_role_id', 'roles_parent_role_id_index');
            $table->index('template_id', 'roles_template_id_index');
            $table->index('level', 'roles_level_index');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        // Cart tables
        Schema::dropIfExists('carts');
        Schema::create('carts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('identifier')->index();
            $table->string('instance')->default('default')->index();
            $table->json('items')->nullable();
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->bigInteger('version')->default(1)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['identifier', 'instance']);
        });

        // Stock tables
        Schema::dropIfExists('stock_transactions');
        Schema::create('stock_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('stockable');
            $table->uuid('user_id')->nullable();
            $table->integer('quantity');
            $table->enum('type', ['in', 'out']);
            $table->string('reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps();
        });

        // Stock reservations table
        Schema::dropIfExists('stock_reservations');
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('stockable');
            $table->string('cart_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->integer('quantity');
            $table->timestamp('expires_at')->index();
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->timestamps();

            $table->index(['stockable_type', 'stockable_id', 'cart_id']);
        });

        // Test support table for stock testing
        Schema::dropIfExists('test_products');
        Schema::create('test_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
}
