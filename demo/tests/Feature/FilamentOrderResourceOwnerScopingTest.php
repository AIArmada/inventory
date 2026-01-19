<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\FilamentOrders\Resources\OrderResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the OrderResource navigation badge for the single tenant', function (): void {
    $owner = User::factory()->create(['email' => 'admin@commerce.demo']);

    OwnerContext::withOwner($owner, function () use ($owner): void {
        Order::create([
            'order_number' => 'ORD-DEMO-0001',
            'status' => PendingPayment::class,
            'subtotal' => 10_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 10_00,
            'currency' => 'MYR',
        ])->assignOwner($owner)->save();
        Order::create([
            'order_number' => 'ORD-DEMO-0002',
            'status' => Processing::class,
            'subtotal' => 20_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 20_00,
            'currency' => 'MYR',
        ])->assignOwner($owner)->save();
    });

    $badge = OwnerContext::withOwner($owner, fn (): ?string => OrderResource::getNavigationBadge());

    expect($badge)->toBe('2');
});

it('scopes the OrderResource list query to the single tenant', function (): void {
    $owner = User::factory()->create(['email' => 'admin@commerce.demo']);

    $orderA = OwnerContext::withOwner($owner, function () use ($owner): Order {
        $order = Order::create([
            'order_number' => 'ORD-DEMO-0001',
            'status' => PendingPayment::class,
            'subtotal' => 10_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 10_00,
            'currency' => 'MYR',
        ]);

        $order->assignOwner($owner)->save();

        return $order;
    });

    $orderB = OwnerContext::withOwner($owner, function () use ($owner): Order {
        $order = Order::create([
            'order_number' => 'ORD-DEMO-0002',
            'status' => Processing::class,
            'subtotal' => 20_00,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => 20_00,
            'currency' => 'MYR',
        ]);

        $order->assignOwner($owner)->save();

        return $order;
    });

    $ids = OwnerContext::withOwner($owner, fn (): array => OrderResource::getEloquentQuery()->pluck('id')->all());

    expect($ids)->toContain($orderA->id);
    expect($ids)->toContain($orderB->id);
});

it('fails closed when no owner is resolved for OrderResource', function (): void {
    OwnerContext::override(null);

    $badge = OrderResource::getNavigationBadge();
    $count = OrderResource::getEloquentQuery()->count();

    OwnerContext::clearOverride();

    expect($badge)->toBeNull();
    expect($count)->toBe(0);
});
