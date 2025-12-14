<?php

declare(strict_types=1);

use AIArmada\Inventory\Exceptions\InsufficientInventoryException;

test('InsufficientInventoryException stores and returns properties correctly', function () {
    $exception = new InsufficientInventoryException(
        'Not enough inventory',
        'item-123',
        10,
        5
    );

    expect($exception->getMessage())->toBe('Not enough inventory');
    expect($exception->getItemId())->toBe('item-123');
    expect($exception->getRequestedQuantity())->toBe(10);
    expect($exception->getAvailableQuantity())->toBe(5);
    expect($exception->getShortfall())->toBe(5);
});

test('InsufficientInventoryException works with integer item ID', function () {
    $exception = new InsufficientInventoryException(
        'Insufficient stock',
        456,
        20,
        15
    );

    expect($exception->getItemId())->toBe(456);
    expect($exception->getShortfall())->toBe(5);
});