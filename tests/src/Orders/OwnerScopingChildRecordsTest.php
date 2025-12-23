<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\States\Created;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('test_owners_child');

    Schema::create('test_owners_child', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', true);
    config()->set('orders.owner.auto_assign_on_create', false);

    // Default to no ambient owner context so seeding explicit-owner rows is deterministic.
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('prevents creating child rows for orders outside the current owner scope', function (): void {
    $ownerA = TestOwnerChild::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwnerChild::query()->create(['name' => 'Owner B']);

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
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(fn () => OrderItem::query()->create([
        'order_id' => $orderB->id,
        'name' => 'Test Item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]))->toThrow(AuthorizationException::class);

    expect(fn () => OrderNote::query()->create([
        'order_id' => $orderB->id,
        'content' => 'Cross-tenant note',
        'is_customer_visible' => false,
    ]))->toThrow(AuthorizationException::class);
});

it('auto-assigns child row owner from the parent order', function (): void {
    $ownerA = TestOwnerChild::query()->create(['name' => 'Owner A']);

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

    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $orderA->id,
        'name' => 'Test Item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    $note = OrderNote::query()->create([
        'order_id' => $orderA->id,
        'content' => 'Internal note',
        'is_customer_visible' => false,
    ]);

    expect($item->owner_type)->toBe($orderA->owner_type)
        ->and($item->owner_id)->toBe($orderA->owner_id)
        ->and($note->owner_type)->toBe($orderA->owner_type)
        ->and($note->owner_id)->toBe($orderA->owner_id);

    $ids = OrderItem::query()->forOwner()->pluck('id')->all();

    expect($ids)->toContain($item->id);
});

class TestOwnerChild extends Model
{
    use HasUuids;

    protected $table = 'test_owners_child';

    protected $fillable = ['name'];
}
