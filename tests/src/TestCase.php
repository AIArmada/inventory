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
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
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
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            HashServiceProvider::class,
            CacheServiceProvider::class,
            DatabaseServiceProvider::class,
            TranslationServiceProvider::class,
            ValidationServiceProvider::class,
            LivewireTestingServiceProvider::class,
            LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Schemas\SchemasServiceProvider::class,
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
            \AIArmada\Products\ProductsServiceProvider::class,
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

        // Configure Spatie Activitylog
        $app['config']->set('activitylog.default_auth_driver', null);
        $app['config']->set('activitylog.enabled', false);

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

        // Configure Spatie Media Library for testing
        $app['config']->set('media-library.media_model', \Spatie\MediaLibrary\MediaCollections\Models\Media::class);
        $app['config']->set('media-library.disk_name', 'public');

        // Configure auth to use our test User model
        $app['config']->set('auth.providers.users.model', Fixtures\Models\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/chip/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/vouchers/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/shipping/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../vendor/spatie/laravel-permission/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/affiliates/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../packages/docs/database/migrations');
    }

    protected function setUpDatabase(): void
    {
        // Media table for Spatie Media Library
        Schema::dropIfExists('media');
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->nullableTimestamps();
        });

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
            $table->string('owner_type')->default('');
            $table->string('owner_id')->default('');
            $table->string('instance')->default('default')->index();
            $table->json('items')->nullable();
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->bigInteger('version')->default(1)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'identifier', 'instance']);
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

        // =========================================================================
        // PRODUCTS PACKAGE TABLES
        // =========================================================================
        Schema::dropIfExists('product_attribute_group_attribute_set');
        Schema::dropIfExists('product_attribute_attribute_set');
        Schema::dropIfExists('product_attribute_attribute_group');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_attribute_sets');
        Schema::dropIfExists('product_attribute_groups');
        Schema::dropIfExists('collection_product');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('product_variant_options');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_collections');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('products');

        Schema::create('products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable();
            $table->string('type')->default('simple');
            $table->string('status')->default('draft');
            $table->string('visibility')->default('catalog_search');
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('compare_price')->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->string('currency', 3)->default('MYR');
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->string('weight_unit', 10)->default('kg');
            $table->string('dimension_unit', 10)->default('cm');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('requires_shipping')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('tax_class')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_collections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('manual');
            $table->json('conditions')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('unpublished_at')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('name');
            $table->integer('position')->default(0);
            $table->boolean('is_visible')->default(true); // Added this
            $table->timestamps();
        });

        Schema::create('product_option_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('option_id');
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->string('swatch_color', 7)->nullable();
            $table->string('swatch_image')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('name')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('price')->nullable();
            $table->unsignedBigInteger('cost')->nullable();
            $table->unsignedBigInteger('compare_price')->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $table): void {
            $table->uuid('category_id');
            $table->uuid('product_id');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->primary(['category_id', 'product_id']);
        });

        Schema::create('collection_product', function (Blueprint $table): void {
            $table->uuid('collection_id');
            $table->uuid('product_id');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->primary(['collection_id', 'product_id']);
        });

        Schema::create('product_variant_options', function (Blueprint $table): void {
            $table->uuid('variant_id');
            $table->uuid('option_value_id');
            $table->primary(['variant_id', 'option_value_id']);
        });

        // Attribute tables
        Schema::create('product_attribute_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('product_attributes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('text');
            $table->json('validation')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_comparable')->default(false);
            $table->boolean('is_visible_on_front')->default(true);
            $table->boolean('is_visible_on_admin')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->string('suffix')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('help_text')->nullable();
            $table->text('default_value')->nullable();
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('attribute_id');
            $table->uuidMorphs('attributable');
            $table->text('value')->nullable();
            $table->string('locale', 10)->nullable();
            $table->timestamps();
            $table->unique(['attribute_id', 'attributable_type', 'attributable_id', 'locale'], 'attr_val_unique');
        });

        Schema::create('product_attribute_sets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // Attribute pivot tables
        Schema::create('product_attribute_attribute_group', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attribute_id');
            $table->uuid('attribute_group_id');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'attribute_group_id']);
        });

        Schema::create('product_attribute_attribute_set', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attribute_id');
            $table->uuid('attribute_set_id');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'attribute_set_id']);
        });

        Schema::create('product_attribute_group_attribute_set', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('attribute_group_id');
            $table->uuid('attribute_set_id');
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->unique(['attribute_group_id', 'attribute_set_id'], 'group_set_unique');
        });

        // =========================================================================
        // CUSTOMERS PACKAGE TABLES
        // =========================================================================
        Schema::dropIfExists('customer_segment');
        Schema::dropIfExists('customer_customer_group');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('customer_groups');
        Schema::dropIfExists('customers');

        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('status')->default('active');
            $table->boolean('accepts_marketing')->default(false);
            $table->boolean('is_tax_exempt')->default(false);
            $table->integer('wallet_balance')->default(0);
            $table->integer('lifetime_value')->default(0);
            $table->integer('total_orders')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->nullableUuidMorphs('owner');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table): void {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        Schema::create('customer_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->nullableUuidMorphs('owner');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_group_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('group_id');
            $table->uuid('customer_id');
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('customer_segments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_automatic')->default(true);
            $table->integer('priority')->default(0);
            $table->nullableUuidMorphs('owner');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('type')->default('shipping');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country');
            $table->string('phone')->nullable();
            $table->string('recipient_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->string('postcode')->nullable();
            $table->string('label')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wishlists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('name')->default('My Wishlist');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('share_token', 64)->unique();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('wishlist_id');
            $table->string('product_type');
            $table->uuid('product_id');
            $table->boolean('notified_on_sale')->default(false);
            $table->boolean('notified_in_stock')->default(false);
            $table->timestamp('added_at')->nullable();
            $table->integer('priority')->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->text('content');
            $table->boolean('is_internal')->default(true);
            $table->boolean('is_pinned')->default(false);
            $table->nullableUuidMorphs('created_by');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_customer_group', function (Blueprint $table): void {
            $table->uuid('customer_id');
            $table->uuid('customer_group_id');
            $table->primary(['customer_id', 'customer_group_id']);
        });

        Schema::create('customer_segment_customer', function (Blueprint $table): void {
            $table->uuid('customer_id');
            $table->uuid('segment_id');
            $table->timestamps();
            $table->primary(['customer_id', 'segment_id']);
        });

        // =========================================================================
        // ORDERS PACKAGE TABLES
        // =========================================================================
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->string('status', 50)->default('created')->index();
            $table->nullableUuidMorphs('customer');
            $table->nullableUuidMorphs('owner');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'created_at']);
            $table->index(['customer_type', 'customer_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->nullableUuidMorphs('purchasable');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('unit_price')->default(0);
            $table->integer('discount_amount')->default(0);
            $table->integer('tax_amount')->default(0);
            $table->integer('total')->default(0);
            $table->string('currency')->default('MYR');
            $table->json('options')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('order_addresses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('type')->default('shipping');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country_code')->default('MY');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('order_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->string('gateway', 50);
            $table->string('transaction_id')->nullable();
            $table->integer('amount')->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->string('status', 20)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('payment_id')->nullable();
            $table->string('gateway', 50);
            $table->string('transaction_id')->nullable();
            $table->integer('amount')->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->string('status', 20)->default('pending');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->foreignUuid('user_id')->nullable();
            $table->text('content');
            $table->boolean('is_customer_visible')->default(false);
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['order_id', 'is_customer_visible']);
        });

        // =========================================================================
        // PRICING PACKAGE TABLES
        // =========================================================================
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('price_tiers');
        Schema::dropIfExists('prices');
        Schema::dropIfExists('price_lists');

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('currency')->default('MYR');
            $table->uuid('customer_id')->nullable();
            $table->uuid('segment_id')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('price_list_id');
            $table->uuidMorphs('priceable');
            $table->integer('amount')->default(0);
            $table->integer('compare_amount')->nullable();
            $table->string('currency')->default('MYR');
            $table->integer('min_quantity')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('price_tiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('price_list_id')->nullable();
            $table->uuidMorphs('tierable');
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->integer('amount')->default(0);
            $table->string('discount_type')->nullable();
            $table->integer('discount_value')->nullable();
            $table->string('currency')->default('MYR');
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('percentage');
            $table->integer('discount_value')->default(0);
            $table->integer('priority')->default(0);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('min_purchase_amount')->nullable();
            $table->integer('min_quantity')->nullable();
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot table for promotion-product/category relationships
        Schema::create('promotionables', function (Blueprint $table): void {
            $table->uuid('promotion_id');
            $table->uuidMorphs('promotionable');
            $table->primary(['promotion_id', 'promotionable_id', 'promotionable_type']);
        });

        // =========================================================================
        // TAX PACKAGE TABLES
        // =========================================================================
        Schema::dropIfExists('tax_exemptions');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('tax_classes');
        Schema::dropIfExists('tax_zones');

        Schema::create('tax_zones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('country');
            $table->json('countries')->nullable();
            $table->json('states')->nullable();
            $table->json('postcodes')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('owner');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('zone_id');
            $table->string('tax_class')->default('standard');
            $table->string('name');
            $table->integer('rate')->default(0);
            $table->integer('priority')->default(0);
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tax_exemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuidMorphs('exemptable');
            $table->foreignUuid('tax_zone_id')->nullable();
            $table->string('reason');
            $table->string('certificate_number')->nullable();
            $table->string('document_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
