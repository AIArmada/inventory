<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('test_owners_child_reads');

    Schema::create('test_owners_child_reads', function (Blueprint $table): void {
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

it('scopes child reads to current owner plus global (items/payments/notes)', function (): void {
    $ownerA = TestOwnerChildReads::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwnerChildReads::query()->create(['name' => 'Owner B']);

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

    $itemA = $orderA->items()->create([
        'name' => 'Item A',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    $paymentA = $orderA->payments()->create([
        'gateway' => 'manual',
        'transaction_id' => 'txn_a',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $noteA = $orderA->orderNotes()->create([
        'user_id' => null,
        'content' => 'Note A',
        'is_customer_visible' => false,
    ]);

    $orderGlobal = Order::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    // Global children are allowed and should be visible when include_global=true
    $itemGlobal = $orderGlobal->items()->create([
        'name' => 'Global Item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    $paymentGlobal = $orderGlobal->payments()->create([
        'gateway' => 'manual',
        'transaction_id' => 'txn_g',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $noteGlobal = $orderGlobal->orderNotes()->create([
        'user_id' => null,
        'content' => 'Global note',
        'is_customer_visible' => false,
    ]);

    // Create owner B records under ownerB resolver so model hooks allow it.
    app()->instance(OwnerResolverInterface::class, new class($ownerB) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $orderB = Order::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $itemB = $orderB->items()->create([
        'name' => 'Item B',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    $paymentB = $orderB->payments()->create([
        'gateway' => 'manual',
        'transaction_id' => 'txn_b',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $noteB = $orderB->orderNotes()->create([
        'user_id' => null,
        'content' => 'Note B',
        'is_customer_visible' => false,
    ]);

    // Switch back to owner A context for assertions.
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

    expect(OrderItem::query()->forOwner(includeGlobal: true)->pluck('id')->all())
        ->toContain($itemA->id, $itemGlobal->id)
        ->not->toContain($itemB->id);

    expect(OrderPayment::query()->forOwner(includeGlobal: true)->pluck('id')->all())
        ->toContain($paymentA->id, $paymentGlobal->id)
        ->not->toContain($paymentB->id);

    expect(OrderNote::query()->forOwner(includeGlobal: true)->pluck('id')->all())
        ->toContain($noteA->id, $noteGlobal->id)
        ->not->toContain($noteB->id);
});

it('can exclude global rows for child reads when includeGlobal is false', function (): void {
    $ownerA = TestOwnerChildReads::query()->create(['name' => 'Owner A']);

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

    $itemA = $orderA->items()->create([
        'name' => 'Item A',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    $paymentA = $orderA->payments()->create([
        'gateway' => 'manual',
        'transaction_id' => 'txn_a',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $noteA = $orderA->orderNotes()->create([
        'user_id' => null,
        'content' => 'Note A',
        'is_customer_visible' => false,
    ]);

    $orderGlobal = Order::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    $itemGlobal = $orderGlobal->items()->create([
        'name' => 'Global Item',
        'quantity' => 1,
        'unit_price' => 1000,
        'currency' => 'MYR',
    ]);

    $paymentGlobal = $orderGlobal->payments()->create([
        'gateway' => 'manual',
        'transaction_id' => 'txn_g',
        'amount' => 10000,
        'currency' => 'MYR',
        'status' => 'completed',
        'paid_at' => now(),
    ]);

    $noteGlobal = $orderGlobal->orderNotes()->create([
        'user_id' => null,
        'content' => 'Global note',
        'is_customer_visible' => false,
    ]);

    expect(OrderItem::query()->forOwner(includeGlobal: false)->pluck('id')->all())
        ->toContain($itemA->id)
        ->not->toContain($itemGlobal->id);

    expect(OrderPayment::query()->forOwner(includeGlobal: false)->pluck('id')->all())
        ->toContain($paymentA->id)
        ->not->toContain($paymentGlobal->id);

    expect(OrderNote::query()->forOwner(includeGlobal: false)->pluck('id')->all())
        ->toContain($noteA->id)
        ->not->toContain($noteGlobal->id);
});

class TestOwnerChildReads extends Model
{
    use HasUuids;

    protected $table = 'test_owners_child_reads';

    protected $fillable = ['name'];
}
