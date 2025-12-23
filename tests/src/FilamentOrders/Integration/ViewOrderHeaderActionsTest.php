<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentOrders\Resources\OrderResource\Pages\ViewOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\OrdersServiceProvider;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
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

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    app()->register(OrdersServiceProvider::class);
});

afterEach(function (): void {
    Mockery::close();
});

function makeAuthedUser(TestOwner $owner): User
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $user = User::query()->create([
        'name' => 'QA',
        'email' => 'qa@example.com',
        'password' => bcrypt('password'),
    ]);

    foreach (['view_order', 'update_order', 'cancel_order'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user->givePermissionTo('view_order');
    $user->givePermissionTo('update_order');
    $user->givePermissionTo('cancel_order');

    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('user')->andReturn($user);
    $guard->shouldReceive('id')->andReturn($user->getKey());

    Filament::shouldReceive('auth')->andReturn($guard);

    return $user;
}

it('evaluates ViewOrder header action authorization + visibility closures', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner A']);

    makeAuthedUser($owner);

    $order = Order::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $page = new class extends ViewOrder
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $actions = $page->headerActions();

    foreach ($actions as $action) {
        $action->record($order);
        $action->isHidden();
    }

    /** @var \Filament\Actions\Action $confirmPayment */
    $confirmPayment = collect($actions)->firstWhere(fn ($action) => $action->getName() === 'confirm_payment');

    expect($confirmPayment)->not->toBeNull();
    expect($confirmPayment->record($order)->isVisible())->toBeTrue();
});

it('evaluates status-dependent header actions for processing orders', function (): void {
    $owner = TestOwner::query()->create(['name' => 'Owner A']);

    makeAuthedUser($owner);

    $order = Order::query()->create([
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
        'status' => Processing::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $page = new class extends ViewOrder
    {
        public function headerActions(): array
        {
            return $this->getHeaderActions();
        }
    };

    $actions = $page->headerActions();

    /** @var \Filament\Actions\Action $shipOrder */
    $shipOrder = collect($actions)->firstWhere(fn ($action) => $action->getName() === 'ship_order');

    expect($shipOrder)->not->toBeNull();
    expect($shipOrder->record($order)->isVisible())->toBeTrue();
});
