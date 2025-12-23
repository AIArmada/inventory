<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentOrders\Widgets\OrderStatusDistributionWidget;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
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
});

it('builds a status distribution chart scoped to the current owner plus global', function (): void {
    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    // Owner A: 2 processing, 1 pending
    Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Processing::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Processing::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    // Owner B: should be ignored
    Order::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'status' => Processing::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);

    // Global: 1 processing
    Order::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'status' => Processing::class,
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

    $widget = app(OrderStatusDistributionWidget::class);

    $method = new ReflectionMethod(OrderStatusDistributionWidget::class, 'getData');
    $method->setAccessible(true);

    /** @var array{datasets: array<int, array{label: string, data: array<int, int>, backgroundColor: array<int, string>}>, labels: array<int, string>} $data */
    $data = $method->invoke($widget);

    expect($data['datasets'][0]['data'])->toBe([1, 3]);
    expect($data['labels'])->toBe(['Pending Payment', 'Processing']);
});
