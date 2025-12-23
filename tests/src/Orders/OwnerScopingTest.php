<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Support\Fixtures\TestOwner;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Schema::dropIfExists('test_owners');

    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', true);
    config()->set('orders.owner.auto_assign_on_create', true);

    // Default to no ambient owner context so seeding explicit-owner rows is deterministic.
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('scopes Order::forOwner() to current owner plus global and excludes corrupt partial-owner rows', function (): void {
    config()->set('orders.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $orderB = Order::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $orderGlobal = Order::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $ordersTable = (string) config('orders.database.tables.orders', 'orders');

    $orderCorruptId = (string) Str::uuid();
    DB::table($ordersTable)->insert([
        'id' => $orderCorruptId,
        'order_number' => 'ORD-CORRUPT-' . Str::random(8),
        'status' => 'created',
        'customer_type' => null,
        'customer_id' => null,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'subtotal' => 10000,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 10000,
        'currency' => 'MYR',
        'notes' => null,
        'internal_notes' => null,
        'metadata' => null,
        'paid_at' => null,
        'shipped_at' => null,
        'delivered_at' => null,
        'canceled_at' => null,
        'cancellation_reason' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $ids = Order::query()->forOwner(includeGlobal: true)->pluck('id')->all();

    expect($ids)
        ->toContain($orderA->id)
        ->toContain($orderGlobal->id)
        ->not->toContain($orderB->id)
        ->not->toContain($orderCorruptId);
});

it('returns strict global-only when owner resolver returns null', function (): void {
    config()->set('orders.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $orderGlobal = Order::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $ordersTable = (string) config('orders.database.tables.orders', 'orders');

    $orderCorruptId = (string) Str::uuid();
    DB::table($ordersTable)->insert([
        'id' => $orderCorruptId,
        'order_number' => 'ORD-CORRUPT-' . Str::random(8),
        'status' => 'created',
        'customer_type' => null,
        'customer_id' => null,
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'subtotal' => 10000,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 10000,
        'currency' => 'MYR',
        'notes' => null,
        'internal_notes' => null,
        'metadata' => null,
        'paid_at' => null,
        'shipped_at' => null,
        'delivered_at' => null,
        'canceled_at' => null,
        'cancellation_reason' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $ids = Order::query()->forOwner()->pluck('id')->all();

    expect($ids)
        ->toContain($orderGlobal->id)
        ->not->toContain($orderA->id)
        ->not->toContain($orderCorruptId);
});

it('rejects explicit owner that does not match current owner context', function (): void {
    config()->set('orders.owner.auto_assign_on_create', false);

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(fn (): Order => Order::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]))->toThrow(InvalidArgumentException::class);
});

it('auto-assigns owner on create when enabled', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $order = Order::query()->create([
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    expect($order->owner_type)->toBe($ownerA->getMorphClass())
        ->and($order->owner_id)->toBe($ownerA->getKey());
});
