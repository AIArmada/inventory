<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Jnt\Models\JntOrder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', true);
    config()->set('jnt.owner.auto_assign_on_create', true);
});

it('returns strict global-only when owner resolver returns null', function (): void {
    config()->set('jnt.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a@example.com',
        'password' => 'secret',
    ]);

    $orderOwned = JntOrder::query()->create([
        'order_id' => 'ORD-A',
        'customer_code' => 'CUST',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $orderGlobal = JntOrder::query()->create([
        'order_id' => 'ORD-GLOBAL',
        'customer_code' => 'CUST',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $orderCorrupt = JntOrder::query()->create([
        'order_id' => 'ORD-CORRUPT',
        'customer_code' => 'CUST',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $ids = JntOrder::query()->forOwner(null, true)->pluck('id')->all();

    expect($ids)
        ->toContain($orderGlobal->id)
        ->not->toContain($orderOwned->id)
        ->not->toContain($orderCorrupt->id);
});

it('returns no rows when owner resolver returns null and global records are disabled', function (): void {
    config()->set('jnt.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a2@example.com',
        'password' => 'secret',
    ]);

    JntOrder::query()->create([
        'order_id' => 'ORD-A2',
        'customer_code' => 'CUST',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    JntOrder::query()->create([
        'order_id' => 'ORD-GLOBAL2',
        'customer_code' => 'CUST',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $ids = JntOrder::query()->forOwner(null, false)->pluck('id')->all();

    expect($ids)->toBe([]);
});

it('auto-assigns owner on create when enabled', function (): void {
    config()->set('jnt.owner.auto_assign_on_create', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a3@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner)
        {
        }

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $order = JntOrder::query()->create([
        'order_id' => 'ORD-A3',
        'customer_code' => 'CUST',
    ]);

    expect($order->owner_type)->toBe($ownerA->getMorphClass())
        ->and($order->owner_id)->toBe($ownerA->getKey());
});
