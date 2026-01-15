<?php

declare(strict_types=1);

use AIArmada\Cart\Exceptions\ProductNotPurchasableException;

describe('ProductNotPurchasableException', function (): void {
    it('can be instantiated with required parameters', function (): void {
        $exception = new ProductNotPurchasableException(
            productId: 'prod-123',
            productName: 'Test Product',
            reason: 'Out of stock'
        );

        expect($exception->productId)->toBe('prod-123')
            ->and($exception->productName)->toBe('Test Product')
            ->and($exception->reason)->toBe('Out of stock')
            ->and($exception->requestedQuantity)->toBeNull()
            ->and($exception->availableStock)->toBeNull()
            ->and($exception->getMessage())->toContain('Test Product')
            ->and($exception->getMessage())->toContain('Out of stock');
    });

    it('includes quantity info in message when provided', function (): void {
        $exception = new ProductNotPurchasableException(
            productId: 'prod-123',
            productName: 'Test Product',
            reason: 'Insufficient stock',
            requestedQuantity: 10,
            availableStock: 5
        );

        expect($exception->getMessage())->toContain('requested: 10')
            ->and($exception->getMessage())->toContain('available: 5');
    });

    it('creates out of stock exception', function (): void {
        $exception = ProductNotPurchasableException::outOfStock(
            'prod-456',
            'Widget',
            10,
            3
        );

        expect($exception->productId)->toBe('prod-456')
            ->and($exception->productName)->toBe('Widget')
            ->and($exception->reason)->toBe('Insufficient stock')
            ->and($exception->requestedQuantity)->toBe(10)
            ->and($exception->availableStock)->toBe(3);
    });

    it('creates inactive product exception', function (): void {
        $exception = ProductNotPurchasableException::inactive('prod-789', 'Discontinued Item');

        expect($exception->productId)->toBe('prod-789')
            ->and($exception->productName)->toBe('Discontinued Item')
            ->and($exception->reason)->toBe('Product is not available for purchase')
            ->and($exception->requestedQuantity)->toBeNull();
    });

    it('creates minimum not met exception', function (): void {
        $exception = ProductNotPurchasableException::minimumNotMet(
            'prod-min',
            'Bulk Item',
            2,
            5
        );

        expect($exception->productId)->toBe('prod-min')
            ->and($exception->reason)->toContain('Minimum quantity is 5')
            ->and($exception->requestedQuantity)->toBe(2);
    });

    it('creates maximum exceeded exception', function (): void {
        $exception = ProductNotPurchasableException::maximumExceeded(
            'prod-max',
            'Limited Item',
            100,
            10
        );

        expect($exception->productId)->toBe('prod-max')
            ->and($exception->reason)->toContain('Maximum quantity is 10')
            ->and($exception->requestedQuantity)->toBe(100);
    });

    it('creates invalid increment exception', function (): void {
        $exception = ProductNotPurchasableException::invalidIncrement(
            'prod-inc',
            'Pack Item',
            5,
            3
        );

        expect($exception->productId)->toBe('prod-inc')
            ->and($exception->reason)->toContain('increments of 3')
            ->and($exception->requestedQuantity)->toBe(5);
    });
});
