<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Stages\FulfillmentStage;
use AIArmada\Cart\Testing\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage();
});

describe('FulfillmentStage', function (): void {
    it('can be instantiated', function (): void {
        $stage = new FulfillmentStage();

        expect($stage)->toBeInstanceOf(FulfillmentStage::class)
            ->and($stage->getName())->toBe('fulfillment');
    });

    it('can set create order callback', function (): void {
        $stage = new FulfillmentStage();
        $result = $stage->onCreateOrder(fn () => ['order_id' => 'order_123']);

        expect($result)->toBe($stage);
    });

    it('can set cancel order callback', function (): void {
        $stage = new FulfillmentStage();
        $result = $stage->onCancelOrder(fn () => null);

        expect($result)->toBe($stage);
    });

    it('should not execute without create order callback', function (): void {
        $stage = new FulfillmentStage();
        $cart = new Cart($this->storage, 'cart-123');

        expect($stage->shouldExecute($cart, []))->toBeFalse();
    });

    it('should execute with create order callback', function (): void {
        $stage = new FulfillmentStage();
        $stage->onCreateOrder(fn () => ['order_id' => 'order_123']);

        $cart = new Cart($this->storage, 'cart-123');

        expect($stage->shouldExecute($cart, []))->toBeTrue();
    });

    it('succeeds without callback configured', function (): void {
        $stage = new FulfillmentStage();
        $cart = new Cart($this->storage, 'cart-123');

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('No order fulfillment configured');
    });

    it('creates order successfully', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new FulfillmentStage();
        $stage->onCreateOrder(fn ($cart, $context) => [
            'order_id' => 'order_abc123',
            'order_number' => 'ORD-001',
        ]);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('Order created')
            ->and($result->data['order_id'])->toBe('order_abc123')
            ->and($result->data['order_number'])->toBe('ORD-001')
            ->and($result->data)->toHaveKey('fulfilled_at');
    });

    it('creates order without order number', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new FulfillmentStage();
        $stage->onCreateOrder(fn ($cart, $context) => [
            'order_id' => 'order_xyz',
        ]);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->data['order_id'])->toBe('order_xyz')
            ->and($result->data)->not->toHaveKey('order_number');
    });

    it('fails when order_id not returned', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new FulfillmentStage();
        $stage->onCreateOrder(fn ($cart, $context) => []);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Order creation did not return an order ID');
    });

    it('handles exception during order creation', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new FulfillmentStage();
        $stage->onCreateOrder(function ($cart, $context): void {
            throw new Exception('Order service unavailable');
        });

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('Order service unavailable')
            ->and($result->errors['fulfillment'])->toBe('Order service unavailable');
    });

    it('supports rollback with cancel order callback', function (): void {
        $stage = new FulfillmentStage();

        expect($stage->supportsRollback())->toBeFalse();

        $stage->onCancelOrder(fn () => null);

        expect($stage->supportsRollback())->toBeTrue();
    });

    it('cancels order on rollback', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $cancelledOrderId = null;

        $stage = new FulfillmentStage();
        $stage->onCancelOrder(function ($orderId) use (&$cancelledOrderId): void {
            $cancelledOrderId = $orderId;
        });

        $context = ['order_id' => 'order_to_cancel'];

        $stage->rollback($cart, $context);

        expect($cancelledOrderId)->toBe('order_to_cancel');
    });

    it('skips rollback when no order id', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cancelCalled = false;

        $stage = new FulfillmentStage();
        $stage->onCancelOrder(function () use (&$cancelCalled): void {
            $cancelCalled = true;
        });

        $stage->rollback($cart, []);

        expect($cancelCalled)->toBeFalse();
    });

    it('skips rollback without cancel order callback', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new FulfillmentStage();

        $context = ['order_id' => 'order_abc'];

        $stage->rollback($cart, $context);

        expect(true)->toBeTrue();
    });

    it('handles exception during order cancellation gracefully', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new FulfillmentStage();
        $stage->onCancelOrder(function (): void {
            throw new Exception('Cancel service unavailable');
        });

        $context = ['order_id' => 'order_abc'];

        $stage->rollback($cart, $context);

        expect(true)->toBeTrue();
    });

    it('passes context to create order callback', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $receivedContext = null;

        $stage = new FulfillmentStage();
        $stage->onCreateOrder(function ($cart, $context) use (&$receivedContext) {
            $receivedContext = $context;

            return ['order_id' => 'order_123'];
        });

        $stage->execute($cart, [
            'shipping_address' => '123 Main St',
            'transaction_id' => 'txn_abc',
        ]);

        expect($receivedContext['shipping_address'])->toBe('123 Main St')
            ->and($receivedContext['transaction_id'])->toBe('txn_abc');
    });

    it('passes cart to create order callback', function (): void {
        $cart = new Cart($this->storage, 'cart-identifier');

        $receivedCart = null;

        $stage = new FulfillmentStage();
        $stage->onCreateOrder(function ($cart, $context) use (&$receivedCart) {
            $receivedCart = $cart;

            return ['order_id' => 'order_123'];
        });

        $stage->execute($cart, []);

        expect($receivedCart)->toBe($cart);
    });
});
