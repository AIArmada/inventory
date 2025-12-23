<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentOrders\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentOrders\Widgets\OrderStatsWidget;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
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

it('calculates stats using an owner-scoped query', function (): void {
    Carbon::setTestNow(Carbon::parse('2025-01-10 10:00:00'));

    $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

    // Today (owner A): 1 paid order
    $orderA = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
        'paid_at' => now(),
    ]);

    $orderA->forceFill(['created_at' => now()->copy()->subHour(), 'updated_at' => now()->copy()->subHour()])->save();

    // Today (owner B): should be ignored
    $orderB = Order::query()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 99999,
        'grand_total' => 99999,
        'paid_at' => now(),
    ]);

    $orderB->forceFill(['created_at' => now()->copy()->subHour(), 'updated_at' => now()->copy()->subHour()])->save();

    // Today (global): 1 paid order
    $orderGlobal = Order::query()->create([
        'owner_type' => null,
        'owner_id' => null,
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 2500,
        'grand_total' => 2500,
        'paid_at' => now(),
    ]);

    $orderGlobal->forceFill(['created_at' => now()->copy()->subHours(2), 'updated_at' => now()->copy()->subHours(2)])->save();

    // Yesterday (owner A): 1 paid order
    $orderYesterday = Order::query()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 5000,
        'grand_total' => 5000,
        'paid_at' => now()->copy()->subDay(),
    ]);

    $orderYesterday->forceFill(['created_at' => now()->copy()->subDay()->subHour(), 'updated_at' => now()->copy()->subDay()->subHour()])->save();

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    $today = now()->startOfDay();

    $scopedTodayOrders = Order::query()->forOwner(includeGlobal: true)->whereDate('created_at', $today)->count();
    $unscopedTodayOrders = Order::query()->withoutOwnerScope()->whereDate('created_at', $today)->count();

    $scopedTodayRevenue = Order::query()->forOwner(includeGlobal: true)->whereDate('created_at', $today)->whereNotNull('paid_at')->sum('grand_total');
    $unscopedTodayRevenue = Order::query()->withoutOwnerScope()->whereDate('created_at', $today)->whereNotNull('paid_at')->sum('grand_total');

    expect($scopedTodayOrders)->toBeLessThan($unscopedTodayOrders);
    expect($scopedTodayRevenue)->toBeLessThan($unscopedTodayRevenue);

    $widget = app(OrderStatsWidget::class);

    $method = new ReflectionMethod(OrderStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    /** @var array<int, \Filament\Widgets\StatsOverviewWidget\Stat> $stats */
    $stats = $method->invoke($widget);

    expect($stats)->toHaveCount(4);
    expect($stats[0]->getValue())->toBe(number_format($scopedTodayOrders));

    $currency = (string) config('orders.currency.default', 'MYR');

    expect($stats[1]->getValue())->toBe($currency . ' ' . number_format($scopedTodayRevenue / 100, 2));
});
