<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentOrders\Resources\OrderResource;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
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
    config()->set('orders.owner.auto_assign_on_create', true);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });
});

it('scopes OrderResource to current owner plus global', function (): void {
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

    $ids = OrderResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)
        ->toContain($orderA->id)
        ->toContain($orderGlobal->id)
        ->not->toContain($orderB->id);
});
