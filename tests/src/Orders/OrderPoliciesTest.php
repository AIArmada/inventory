<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Policies\OrderItemPolicy;
use AIArmada\Orders\Policies\OrderPolicy;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use Illuminate\Foundation\Auth\User;

describe('Order Policies', function (): void {
    describe('OrderPolicy', function (): void {
        it('allows viewing orders when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_any_order')->andReturn(true);
            $user->shouldReceive('can')->with('view_order')->andReturn(true);

            $policy = new OrderPolicy;

            expect($policy->viewAny($user))->toBeTrue();
            expect($policy->view($user, new Order))->toBeTrue();
        });

        it('denies viewing orders when user lacks permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_any_order')->andReturn(false);
            $user->shouldReceive('can')->with('view_order')->andReturn(false);

            $policy = new OrderPolicy;

            expect($policy->viewAny($user))->toBeFalse();
            expect($policy->view($user, new Order))->toBeFalse();
        });

        it('allows creating orders when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('create_order')->andReturn(true);

            $policy = new OrderPolicy;

            expect($policy->create($user))->toBeTrue();
        });

        it('denies creating orders when user lacks permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('create_order')->andReturn(false);

            $policy = new OrderPolicy;

            expect($policy->create($user))->toBeFalse();
        });

        it('allows updating non-final orders when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->update($user, $order))->toBeTrue();
        });

        it('denies updating final orders even with permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY2-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->update($user, $order))->toBeFalse();
        });

        it('allows adding notes to final orders when user can update orders (and can view)', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_order')->andReturn(true);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY-NOTE1-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->addNote($user, $order))->toBeTrue();
        });

        it('allows adding notes to final orders when user has add_order_note permission (and can view)', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_order')->andReturn(true);
            $user->shouldReceive('can')->with('update_order')->andReturn(false);
            $user->shouldReceive('can')->with('add_order_note')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY-NOTE2-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->addNote($user, $order))->toBeTrue();
        });

        it('denies adding notes when user cannot view the order', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_order')->andReturn(false);

            $order = Order::create([
                'order_number' => 'ORD-POLICY-NOTE3-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->addNote($user, $order))->toBeFalse();
        });

        it('denies adding notes when user can view but lacks both update and add_order_note permissions', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_order')->andReturn(true);
            $user->shouldReceive('can')->with('update_order')->andReturn(false);
            $user->shouldReceive('can')->with('add_order_note')->andReturn(false);

            $order = Order::create([
                'order_number' => 'ORD-POLICY-NOTE4-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->addNote($user, $order))->toBeFalse();
        });

        it('allows deleting orders when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('delete_order')->andReturn(true);

            $policy = new OrderPolicy;

            expect($policy->delete($user, new Order))->toBeTrue();
        });

        it('allows canceling cancellable orders when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('cancel_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->cancel($user, $order))->toBeTrue();
        });

        it('denies canceling non-cancellable orders even with permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('cancel_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY4-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->cancel($user, $order))->toBeFalse();
        });

        it('allows refunding refundable orders when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('refund_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY5-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->refund($user, $order))->toBeTrue();
        });

        it('denies refunding non-refundable orders even with permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('refund_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-POLICY6-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $policy = new OrderPolicy;

            expect($policy->refund($user, $order))->toBeFalse();
        });
    });

    describe('OrderItemPolicy', function (): void {
        it('allows viewing order items when user has order view permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('view_any_order')->andReturn(true);
            $user->shouldReceive('can')->with('view_order')->andReturn(true);

            $policy = new OrderItemPolicy;

            expect($policy->viewAny($user))->toBeTrue();
            expect($policy->view($user, new OrderItem))->toBeTrue();
        });

        it('allows creating order items when user has order create permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('create_order')->andReturn(true);

            $policy = new OrderItemPolicy;

            expect($policy->create($user))->toBeTrue();
        });

        it('allows updating non-final order items when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-ITEM-POLICY1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 10000,
            ]);

            $policy = new OrderItemPolicy;

            expect($policy->update($user, $item))->toBeTrue();
        });

        it('denies updating final order items even with permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-ITEM-POLICY2-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 10000,
            ]);

            $policy = new OrderItemPolicy;

            expect($policy->update($user, $item))->toBeFalse();
        });

        it('allows deleting non-final order items when user has permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-ITEM-POLICY3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 10000,
            ]);

            $policy = new OrderItemPolicy;

            expect($policy->delete($user, $item))->toBeTrue();
        });

        it('denies deleting final order items even with permission', function (): void {
            $user = Mockery::mock(User::class);
            $user->shouldReceive('can')->with('update_order')->andReturn(true);

            $order = Order::create([
                'order_number' => 'ORD-ITEM-POLICY4-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 10000,
            ]);

            $policy = new OrderItemPolicy;

            expect($policy->delete($user, $item))->toBeFalse();
        });
    });
});
