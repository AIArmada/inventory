<?php

declare(strict_types=1);

use AIArmada\Inventory\Facades\InventoryAllocation;
use AIArmada\Inventory\Services\InventoryAllocationService;

test('InventoryAllocation facade has correct accessor method', function (): void {
    $reflection = new ReflectionClass(InventoryAllocation::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    expect($method->invoke(null))->toBe(InventoryAllocationService::class);
});
