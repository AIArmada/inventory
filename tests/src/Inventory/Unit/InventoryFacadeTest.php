<?php

declare(strict_types=1);

use AIArmada\Inventory\Facades\Inventory;
use AIArmada\Inventory\Services\InventoryService;

test('Inventory facade has correct accessor method', function (): void {
    $reflection = new ReflectionClass(Inventory::class);
    $method = $reflection->getMethod('getFacadeAccessor');
    expect($method->invoke(null))->toBe(InventoryService::class);
});
