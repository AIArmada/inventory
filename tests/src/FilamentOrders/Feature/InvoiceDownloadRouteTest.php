<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentOrders\FilamentOrdersServiceProvider;
use AIArmada\Orders\Actions\GenerateInvoice;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Orders\States\Created;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', true);
    config()->set('orders.owner.auto_assign_on_create', false);

    app()->register(OrdersServiceProvider::class);
    app()->register(FilamentOrdersServiceProvider::class);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    app()->instance(GenerateInvoice::class, new class
    {
        public function download(Order $order)
        {
            return response('invoice:' . $order->getKey(), 200);
        }
    });
});

it('returns 404 for cross-tenant invoice downloads', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);
    $guard->shouldReceive('id')->andReturn($user->getKey());

    Filament::shouldReceive('auth')->andReturn($guard);

    $orderB = Order::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Permission::create(['name' => 'view_order', 'guard_name' => 'web']);
    $user->givePermissionTo('view_order');

    $this->withoutMiddleware();

    $this->get(route('filament-orders.invoice.download', ['order' => $orderB->getKey()]))
        ->assertNotFound();
});

it('returns 403 when the user cannot view the order', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test2@example.com',
        'password' => bcrypt('password'),
    ]);

    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);
    $guard->shouldReceive('id')->andReturn($user->getKey());

    Filament::shouldReceive('auth')->andReturn($guard);

    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $this->withoutMiddleware();

    $this->get(route('filament-orders.invoice.download', ['order' => $orderA->getKey()]))
        ->assertForbidden();
});

it('downloads an invoice for an in-scope order', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test3@example.com',
        'password' => bcrypt('password'),
    ]);

    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);
    $guard->shouldReceive('id')->andReturn($user->getKey());

    Filament::shouldReceive('auth')->andReturn($guard);

    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Permission::create(['name' => 'view_order', 'guard_name' => 'web']);
    $user->givePermissionTo('view_order');

    $this->withoutMiddleware();

    $this->get(route('filament-orders.invoice.download', ['order' => $orderA->getKey()]))
        ->assertOk()
        ->assertSee('invoice:' . $orderA->getKey());
});

it('returns 404 when owner context is missing', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test4@example.com',
        'password' => bcrypt('password'),
    ]);

    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);
    $guard->shouldReceive('id')->andReturn($user->getKey());

    Filament::shouldReceive('auth')->andReturn($guard);

    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    Permission::create(['name' => 'view_order', 'guard_name' => 'web']);
    $user->givePermissionTo('view_order');

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $this->withoutMiddleware();

    $this->get(route('filament-orders.invoice.download', ['order' => $orderA->getKey()]))
        ->assertNotFound();
});
