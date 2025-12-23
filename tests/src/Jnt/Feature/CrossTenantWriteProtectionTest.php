<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntOrderItem;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config()->set('jnt.owner.enabled', true);
    config()->set('jnt.owner.include_global', false);
    config()->set('jnt.owner.auto_assign_on_create', true);
});

it('prevents cross-tenant writes via order_id on child models', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-ct@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-ct@example.com',
        'password' => 'secret',
    ]);

    $orderA = JntOrder::query()->create([
        'order_id' => 'ORD-CT-A',
        'customer_code' => 'CUST',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $orderB = JntOrder::query()->create([
        'order_id' => 'ORD-CT-B',
        'customer_code' => 'CUST',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    expect(fn (): JntOrderItem => JntOrderItem::query()->create([
        'order_id' => $orderB->id,
        'name' => 'Widget',
        'quantity' => 1,
        'weight_grams' => 100,
        'unit_price' => '10.00',
        'currency' => 'MYR',
    ]))->toThrow(InvalidArgumentException::class);

    $itemForOrderA = JntOrderItem::query()->create([
        'order_id' => $orderA->id,
        'name' => 'Widget',
        'quantity' => 1,
        'weight_grams' => 100,
        'unit_price' => '10.00',
        'currency' => 'MYR',
    ]);

    expect($itemForOrderA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($itemForOrderA->owner_id)->toBe($ownerA->getKey());
});
