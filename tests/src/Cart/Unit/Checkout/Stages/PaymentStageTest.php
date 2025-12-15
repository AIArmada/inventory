<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Stages\PaymentStage;
use AIArmada\Cart\Testing\InMemoryStorage;

describe('PaymentStage', function (): void {
    beforeEach(function (): void {
        $this->storage = new InMemoryStorage;
    });

    it('can be instantiated', function (): void {
        $stage = new PaymentStage();

        expect($stage)->toBeInstanceOf(PaymentStage::class)
            ->and($stage->getName())->toBe('payment');
    });

    it('can set process callback', function (): void {
        $stage = new PaymentStage();
        $result = $stage->onProcess(fn () => ['success' => true]);

        expect($result)->toBe($stage);
    });

    it('can set refund callback', function (): void {
        $stage = new PaymentStage();
        $result = $stage->onRefund(fn () => true);

        expect($result)->toBe($stage);
    });

    it('should not execute for zero total cart', function (): void {
        $stage = new PaymentStage();
        $stage->onProcess(fn () => ['success' => true]);

        $cart = new Cart($this->storage, 'cart-123');
        // Empty cart has zero total

        expect($stage->shouldExecute($cart, []))->toBeFalse();
    });

    it('should not execute without process callback', function (): void {
        $stage = new PaymentStage();

        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 1000, 1);

        expect($stage->shouldExecute($cart, []))->toBeFalse();
    });

    it('should execute with process callback and positive total', function (): void {
        $stage = new PaymentStage();
        $stage->onProcess(fn () => ['success' => true]);

        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 1000, 1);

        expect($stage->shouldExecute($cart, []))->toBeTrue();
    });

    it('succeeds without callback configured', function (): void {
        $stage = new PaymentStage();
        $cart = new Cart($this->storage, 'cart-123');

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('No payment processing configured');
    });

    it('processes payment successfully', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $stage = new PaymentStage();
        $stage->onProcess(fn ($cart, $context) => [
            'success' => true,
            'transaction_id' => 'txn_abc123',
        ]);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('Payment processed')
            ->and($result->data['transaction_id'])->toBe('txn_abc123')
            ->and($result->data['payment_amount_cents'])->toBe(5000)
            ->and($result->data)->toHaveKey('payment_processed_at');
    });

    it('includes payment URL for redirect flows', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 10000, 1);

        $stage = new PaymentStage();
        $stage->onProcess(fn ($cart, $context) => [
            'success' => true,
            'payment_url' => 'https://payment.example.com/checkout/xyz',
        ]);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->data['payment_url'])->toBe('https://payment.example.com/checkout/xyz')
            ->and($result->data['requires_redirect'])->toBeTrue();
    });

    it('fails when payment is declined', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $stage = new PaymentStage();
        $stage->onProcess(fn ($cart, $context) => [
            'success' => false,
            'message' => 'Card declined',
        ]);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Card declined')
            ->and($result->errors['payment'])->toBe('Card declined');
    });

    it('uses default error message when none provided', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $stage = new PaymentStage();
        $stage->onProcess(fn ($cart, $context) => ['success' => false]);

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Payment processing failed')
            ->and($result->errors['payment'])->toBe('Unknown error');
    });

    it('handles exception during payment processing', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $stage = new PaymentStage();
        $stage->onProcess(function ($cart, $context): void {
            throw new Exception('Payment gateway timeout');
        });

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toContain('Payment gateway timeout')
            ->and($result->errors['payment'])->toBe('Payment gateway timeout');
    });

    it('supports rollback with refund callback', function (): void {
        $stage = new PaymentStage();

        expect($stage->supportsRollback())->toBeFalse();

        $stage->onRefund(fn () => true);

        expect($stage->supportsRollback())->toBeTrue();
    });

    it('refunds payment on rollback', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $refunded = false;
        $refundedAmount = 0;
        $refundedTransactionId = null;

        $stage = new PaymentStage();
        $stage->onRefund(function ($transactionId, $amountCents) use (&$refunded, &$refundedAmount, &$refundedTransactionId) {
            $refunded = true;
            $refundedTransactionId = $transactionId;
            $refundedAmount = $amountCents;

            return true;
        });

        $context = [
            'transaction_id' => 'txn_abc123',
            'payment_amount_cents' => 5000,
        ];

        $stage->rollback($cart, $context);

        expect($refunded)->toBeTrue()
            ->and($refundedTransactionId)->toBe('txn_abc123')
            ->and($refundedAmount)->toBe(5000);
    });

    it('uses cart total when payment amount not in context', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 3000, 1);

        $refundedAmount = 0;

        $stage = new PaymentStage();
        $stage->onRefund(function ($transactionId, $amountCents) use (&$refundedAmount) {
            $refundedAmount = $amountCents;

            return true;
        });

        $context = ['transaction_id' => 'txn_xyz'];

        $stage->rollback($cart, $context);

        expect($refundedAmount)->toBe(3000);
    });

    it('skips rollback when no transaction id', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $refundCalled = false;

        $stage = new PaymentStage();
        $stage->onRefund(function () use (&$refundCalled) {
            $refundCalled = true;

            return true;
        });

        $stage->rollback($cart, []);

        expect($refundCalled)->toBeFalse();
    });

    it('skips rollback without refund callback', function (): void {
        $cart = new Cart($this->storage, 'cart-123');

        $stage = new PaymentStage();

        $context = ['transaction_id' => 'txn_abc'];

        $stage->rollback($cart, $context);

        expect(true)->toBeTrue();
    });

    it('handles exception during refund gracefully', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $stage = new PaymentStage();
        $stage->onRefund(function (): void {
            throw new Exception('Refund service unavailable');
        });

        $context = ['transaction_id' => 'txn_abc'];

        $stage->rollback($cart, $context);

        expect(true)->toBeTrue();
    });

    it('passes context to process callback', function (): void {
        $cart = new Cart($this->storage, 'cart-123');
        $cart->add('item-1', 'Product', 5000, 1);

        $receivedContext = null;

        $stage = new PaymentStage();
        $stage->onProcess(function ($cart, $context) use (&$receivedContext) {
            $receivedContext = $context;

            return ['success' => true];
        });

        $stage->execute($cart, ['custom_key' => 'custom_value']);

        expect($receivedContext['custom_key'])->toBe('custom_value');
    });

    it('handles zero cart total as skip payment', function (): void {
        $stage = new PaymentStage();
        $stage->onProcess(fn () => ['success' => true]);

        $cart = new Cart($this->storage, 'cart-123');
        // Empty cart has zero total

        expect($stage->shouldExecute($cart, []))->toBeFalse();
    });
});
