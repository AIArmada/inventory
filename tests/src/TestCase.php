<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Start session for Livewire/Filament tests
        $this->app['session']->start();

        // Share an empty error bag so Blade always receives the expected variable
        $this->app['view']->share('errors', tap(new ViewErrorBag(), static function (ViewErrorBag $bag): void {
            $bag->put('default', new MessageBag());
        }));

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Illuminate\Events\EventServiceProvider::class,
            \Illuminate\Session\SessionServiceProvider::class,
            \Illuminate\View\ViewServiceProvider::class,
            \Illuminate\Hashing\HashServiceProvider::class,
            \Illuminate\Cache\CacheServiceProvider::class,
            \Illuminate\Database\DatabaseServiceProvider::class,
            \Illuminate\Translation\TranslationServiceProvider::class,
            \Illuminate\Validation\ValidationServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            \AIArmada\Cart\CartServiceProvider::class,
            \AIArmada\Chip\ChipServiceProvider::class,
            \AIArmada\Jnt\JntServiceProvider::class,
            \AIArmada\Docs\DocsServiceProvider::class,
            \AIArmada\Stock\StockServiceProvider::class,
            \AIArmada\Vouchers\VoucherServiceProvider::class,
            \AIArmada\FilamentCart\FilamentCartServiceProvider::class,
            \AIArmada\FilamentChip\FilamentChipServiceProvider::class,
            \Spatie\Permission\PermissionServiceProvider::class,
            \AIArmada\FilamentPermissions\FilamentPermissionsServiceProvider::class,
            \AIArmada\FilamentVouchers\FilamentVouchersServiceProvider::class,
            \AIArmada\Affiliates\AffiliatesServiceProvider::class,
            \AIArmada\FilamentAffiliates\FilamentAffiliatesServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Cart' => \AIArmada\Cart\Facades\Cart::class,
            'Voucher' => \AIArmada\Vouchers\Facades\Voucher::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Setup the test environment
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
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

        // Configure cart settings for testing
        $app['config']->set('cart.storage', 'database');
        $app['config']->set('cart.database.connection', 'testing');
        $app['config']->set('cart.database.table', 'carts');
        $app['config']->set('cart.events', true);

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

        // Configure Spatie Permission settings for testing
        $app['config']->set('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        $app['config']->set('permission.models.role', \Spatie\Permission\Models\Role::class);
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

        // Configure filament-permissions settings for testing
        $app['config']->set('filament-permissions.guards', ['web', 'admin']);
        $app['config']->set('filament-permissions.super_admin_role', 'Super Admin');

        // Configure auth to use our test User model
        $app['config']->set('auth.providers.users.model', Fixtures\Models\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../packages/chip/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../packages/vouchers/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/spatie/laravel-permission/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../packages/affiliates/database/migrations');
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
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
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

        // Docs tables
        Schema::dropIfExists('docs');
        Schema::dropIfExists('doc_histories');
        Schema::dropIfExists('doc_templates');

        Schema::create('doc_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('view_name');
            $table->string('doc_type')->default('invoice');
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('docs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique();
            $table->string('doc_type')->default('invoice');
            $table->foreignUuid('doc_template_id')->nullable()->constrained('doc_templates')->nullOnDelete();
            $table->nullableUuidMorphs('docable');
            $table->string('status')->default('draft');
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('currency', 3)->default('MYR');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->json('customer_data')->nullable();
            $table->json('company_data')->nullable();
            $table->json('items')->nullable();
            $table->json('metadata')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });

        Schema::create('doc_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('doc_id')->constrained('docs')->cascadeOnDelete();
            $table->string('action');
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
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
