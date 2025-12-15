<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Checkout\Stages\ReservationStage;
use AIArmada\Cart\Testing\InMemoryStorage;

beforeEach(function (): void {
    $this->storage = new InMemoryStorage();
});

describe('ReservationStage', function (): void {
    it('can be instantiated', function (): void {
        $stage = new ReservationStage();

        expect($stage)->toBeInstanceOf(ReservationStage::class)
            ->and($stage->getName())->toBe('reservation');
    });

    it('can set reserve callback', function (): void {
        $stage = new ReservationStage();
        $result = $stage->onReserve(fn () => true);

        expect($result)->toBe($stage);
    });

    it('can set release callback', function (): void {
        $stage = new ReservationStage();
        $result = $stage->onRelease(fn () => null);

        expect($result)->toBe($stage);
    });

    it('should not execute without reserve callback', function (): void {
        $stage = new ReservationStage();
        $cart = new Cart($this->storage, 'user-123');

        expect($stage->shouldExecute($cart, []))->toBeFalse();
    });

    it('should execute with reserve callback', function (): void {
        $stage = new ReservationStage();
        $stage->onReserve(fn () => true);

        $cart = new Cart($this->storage, 'user-123');

        expect($stage->shouldExecute($cart, []))->toBeTrue();
    });

    it('succeeds without callback configured', function (): void {
        $stage = new ReservationStage();
        $cart = new Cart($this->storage, 'user-123');

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('No inventory reservation configured');
    });

    it('reserves all items successfully', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 1000, 2);
        $cart->add('item-2', 'Product 2', 2000, 3);

        $reservedItems = [];

        $stage = new ReservationStage();
        $stage->onReserve(function ($itemId, $quantity, $cart) use (&$reservedItems) {
            $reservedItems[$itemId] = $quantity;

            return true;
        });

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeTrue()
            ->and($result->message)->toBe('Inventory reserved')
            ->and($result->data['reserved_items'])->toBe(['item-1' => 2, 'item-2' => 3])
            ->and($reservedItems)->toBe(['item-1' => 2, 'item-2' => 3]);
    });

    it('fails when reservation returns false', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 1000, 2);
        $cart->add('item-2', 'Out of Stock', 2000, 10);

        $stage = new ReservationStage();
        $stage->onReserve(function ($itemId, $quantity, $cart) {
            return $itemId !== 'item-2';
        });

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->message)->toBe('Inventory reservation failed')
            ->and($result->errors)->toHaveKey('item-2');
    });

    it('rolls back already reserved items on failure', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 1000, 2);
        $cart->add('item-2', 'Out of Stock', 2000, 10);

        $released = [];

        $stage = new ReservationStage();
        $stage->onReserve(function ($itemId, $quantity, $cart) {
            return $itemId !== 'item-2';
        });
        $stage->onRelease(function ($itemId, $quantity, $cart) use (&$released): void {
            $released[$itemId] = $quantity;
        });

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($released)->toBe(['item-1' => 2]);
    });

    it('handles exception during reservation', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product 1', 1000, 1);

        $stage = new ReservationStage();
        $stage->onReserve(function ($itemId, $quantity, $cart): void {
            throw new Exception('Inventory service unavailable');
        });

        $result = $stage->execute($cart, []);

        expect($result->success)->toBeFalse()
            ->and($result->errors['item-1'])->toBe('Inventory service unavailable');
    });

    it('supports rollback with release callback', function (): void {
        $stage = new ReservationStage();

        expect($stage->supportsRollback())->toBeFalse();

        $stage->onRelease(fn () => null);

        expect($stage->supportsRollback())->toBeTrue();
    });

    it('does not support rollback without release callback', function (): void {
        $stage = new ReservationStage();
        $stage->onReserve(fn () => true);

        expect($stage->supportsRollback())->toBeFalse();
    });

    it('releases reserved items on rollback', function (): void {
        $cart = new Cart($this->storage, 'user-123');

        $released = [];

        $stage = new ReservationStage();
        $stage->onRelease(function ($itemId, $quantity, $cart) use (&$released): void {
            $released[$itemId] = $quantity;
        });

        $context = [
            'reserved_items' => [
                'item-1' => 2,
                'item-2' => 3,
            ],
        ];

        $stage->rollback($cart, $context);

        expect($released)->toBe(['item-1' => 2, 'item-2' => 3]);
    });

    it('handles exception during rollback gracefully', function (): void {
        $cart = new Cart($this->storage, 'user-123');

        $stage = new ReservationStage();
        $stage->onRelease(function ($itemId, $quantity, $cart): void {
            throw new Exception('Release failed');
        });

        $context = [
            'reserved_items' => ['item-1' => 1],
        ];

        $stage->rollback($cart, $context);

        expect(true)->toBeTrue(); // No exception propagated
    });

    it('skips rollback when no items were reserved', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $releaseCallCount = 0;

        $stage = new ReservationStage();
        $stage->onRelease(function () use (&$releaseCallCount): void {
            $releaseCallCount++;
        });

        $stage->rollback($cart, []);

        expect($releaseCallCount)->toBe(0);
    });

    it('does nothing on rollback without release callback', function (): void {
        $cart = new Cart($this->storage, 'user-123');

        $stage = new ReservationStage();

        $context = [
            'reserved_items' => ['item-1' => 2],
        ];

        $stage->rollback($cart, $context);

        expect(true)->toBeTrue();
    });

    it('includes reserved_at timestamp in result', function (): void {
        $cart = new Cart($this->storage, 'user-123');
        $cart->add('item-1', 'Product', 1000, 1);

        $stage = new ReservationStage();
        $stage->onReserve(fn () => true);

        $result = $stage->execute($cart, []);

        expect($result->data)->toHaveKey('reserved_at');
    });
});
