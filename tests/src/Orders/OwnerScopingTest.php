<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

    $orderCorrupt = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
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

    $ids = Order::query()->forOwner()->pluck('id')->all();

    expect($ids)
        ->toContain($orderA->id)
        ->toContain($orderGlobal->id)
        ->not->toContain($orderB->id)
        ->not->toContain($orderCorrupt->id);
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

    $orderCorrupt = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
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
        ->not->toContain($orderCorrupt->id);
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

class TestOwner extends Model
{
    use HasUuids;

    protected $table = 'test_owners';

    protected $fillable = ['name'];
}
