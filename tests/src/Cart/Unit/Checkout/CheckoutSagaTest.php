<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\CheckoutResult;
use AIArmada\Cart\Checkout\CheckoutSaga;
use AIArmada\Cart\Checkout\Contracts\CheckoutStageInterface;
use AIArmada\Cart\Checkout\Exceptions\CheckoutException;
use AIArmada\Cart\Checkout\StageResult;
use AIArmada\Cart\Contracts\CartValidationResult;
use AIArmada\Cart\Contracts\CartValidatorInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage;
});

describe('CheckoutSaga', function (): void {
    it('can be created for a cart', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $saga = CheckoutSaga::for($cart);

        expect($saga)->toBeInstanceOf(CheckoutSaga::class);
    });

    it('can be instantiated directly', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $saga = new CheckoutSaga($cart);

        expect($saga)->toBeInstanceOf(CheckoutSaga::class);
    });

    it('can add a validator', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $validator = Mockery::mock(CartValidatorInterface::class);

        $saga = CheckoutSaga::for($cart);
        $result = $saga->addValidator($validator);

        expect($result)->toBe($saga);
    });

    it('can configure inventory callbacks', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $saga = CheckoutSaga::for($cart);
        $result = $saga->withInventory(
            reserve: fn ($itemId, $quantity, $cart) => true,
            release: fn ($itemId, $quantity, $cart) => null
        );

        expect($result)->toBe($saga);
    });

    it('can configure payment callbacks', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $saga = CheckoutSaga::for($cart);
        $result = $saga->withPayment(
            process: fn ($cart, $context) => ['success' => true, 'transaction_id' => 'txn_123'],
            refund: fn ($transactionId, $amountCents) => true
        );

        expect($result)->toBe($saga);
    });

    it('can configure fulfillment callbacks', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $saga = CheckoutSaga::for($cart);
        $result = $saga->withFulfillment(
            create: fn ($cart, $context) => ['order_id' => 'order_123'],
            cancel: fn ($orderId) => null
        );

        expect($result)->toBe($saga);
    });

    it('can add custom stages', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $customStage = Mockery::mock(CheckoutStageInterface::class);

        $saga = CheckoutSaga::for($cart);
        $result = $saga->addStageAfter('validation', $customStage);

        expect($result)->toBe($saga);
    });

    it('can set context', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $saga = CheckoutSaga::for($cart);
        $result = $saga->withContext(['shipping_address' => '123 Main St']);

        expect($result)->toBe($saga);
    });

    it('can disable transactions', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $saga = CheckoutSaga::for($cart);
        $result = $saga->withoutTransaction();

        expect($result)->toBe($saga);
    });

    it('executes checkout with validation stage', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart);
        $result = $saga->execute();

        expect($result)->toBeInstanceOf(CheckoutResult::class)
            ->and($result->success)->toBeTrue();
    });

    it('executes without transaction when disabled', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        DB::shouldReceive('transaction')->never();

        $saga = CheckoutSaga::for($cart)
            ->withoutTransaction();
        $result = $saga->execute();

        expect($result)->toBeInstanceOf(CheckoutResult::class);
    });

    it('fails validation for empty cart', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        // Cart is empty

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart);

        expect(fn () => $saga->execute())->toThrow(CheckoutException::class, 'Cart is empty');
    });

    it('executes inventory reservation', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 2);

        $reserved = false;

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withInventory(
                reserve: function ($itemId, $quantity, $cart) use (&$reserved) {
                    $reserved = true;

                    return true;
                },
                release: fn ($itemId, $quantity, $cart) => null
            );

        $result = $saga->execute();

        expect($result->success)->toBeTrue()
            ->and($reserved)->toBeTrue();
    });

    it('executes payment processing', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 5000, 1);

        $paymentProcessed = false;

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withPayment(
                process: function ($cart, $context) use (&$paymentProcessed) {
                    $paymentProcessed = true;

                    return ['success' => true, 'transaction_id' => 'txn_abc123'];
                }
            );

        $result = $saga->execute();

        expect($result->success)->toBeTrue()
            ->and($paymentProcessed)->toBeTrue()
            ->and($result->context['transaction_id'])->toBe('txn_abc123');
    });

    it('skips payment for zero total cart', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Free Product', 0, 1);

        $paymentProcessed = false;

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withPayment(
                process: function ($cart, $context) use (&$paymentProcessed) {
                    $paymentProcessed = true;

                    return ['success' => true];
                }
            );

        $result = $saga->execute();

        expect($result->success)->toBeTrue()
            ->and($paymentProcessed)->toBeFalse();
    });

    it('executes fulfillment', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        $orderCreated = false;

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withFulfillment(
                create: function ($cart, $context) use (&$orderCreated) {
                    $orderCreated = true;

                    return ['order_id' => 'order_123', 'order_number' => 'ORD-001'];
                }
            );

        $result = $saga->execute();

        expect($result->success)->toBeTrue()
            ->and($orderCreated)->toBeTrue()
            ->and($result->context['order_id'])->toBe('order_123')
            ->and($result->context['order_number'])->toBe('ORD-001');
    });

    it('runs custom validators', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        $validationResult = new CartValidationResult(true, 'Valid');

        $validator = Mockery::mock(CartValidatorInterface::class);
        $validator->shouldReceive('getType')->andReturn('custom');
        $validator->shouldReceive('getPriority')->andReturn(1);
        $validator->shouldReceive('validateCart')->andReturn($validationResult);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->addValidator($validator);

        $result = $saga->execute();

        expect($result->success)->toBeTrue();
    });

    it('fails on custom validator rejection', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        $validationResult = new CartValidationResult(false, 'Item not in stock');

        $validator = Mockery::mock(CartValidatorInterface::class);
        $validator->shouldReceive('getType')->andReturn('inventory');
        $validator->shouldReceive('getPriority')->andReturn(1);
        $validator->shouldReceive('validateCart')->andReturn($validationResult);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->addValidator($validator);

        expect(fn () => $saga->execute())->toThrow(CheckoutException::class);
    });

    it('handles payment failure', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 5000, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withPayment(
                process: fn ($cart, $context) => ['success' => false, 'message' => 'Card declined']
            );

        expect(fn () => $saga->execute())->toThrow(CheckoutException::class, 'Card declined');
    });

    it('handles fulfillment failure', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withFulfillment(
                create: fn ($cart, $context) => [] // Missing order_id
            );

        expect(fn () => $saga->execute())->toThrow(CheckoutException::class);
    });

    it('executes custom stage after validation', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 1000, 1);

        $customStageExecuted = false;

        $customStage = Mockery::mock(CheckoutStageInterface::class);
        $customStage->shouldReceive('getName')->andReturn('custom_fraud_check');
        $customStage->shouldReceive('shouldExecute')->andReturn(true);
        $customStage->shouldReceive('execute')->andReturnUsing(function () use (&$customStageExecuted) {
            $customStageExecuted = true;

            return StageResult::success('Fraud check passed');
        });

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->addStageAfter('validation', $customStage);

        $result = $saga->execute();

        expect($result->success)->toBeTrue()
            ->and($customStageExecuted)->toBeTrue();
    });

    it('includes payment URL for redirect flows', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Test Product', 10000, 1);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($callback) => $callback());

        $saga = CheckoutSaga::for($cart)
            ->withPayment(
                process: fn ($cart, $context) => [
                    'success' => true,
                    'payment_url' => 'https://payment.example.com/checkout/xyz',
                ]
            );

        $result = $saga->execute();

        expect($result->success)->toBeTrue()
            ->and($result->context['payment_url'])->toBe('https://payment.example.com/checkout/xyz')
            ->and($result->context['requires_redirect'])->toBeTrue();
    });
});
