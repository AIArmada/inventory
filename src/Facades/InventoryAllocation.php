<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Facades;

use AIArmada\Inventory\Services\InventoryAllocationService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Database\Eloquent\Collection<int, \AIArmada\Inventory\Models\InventoryAllocation> allocate(\Illuminate\Database\Eloquent\Model $model, int $quantity, string $cartId, int $ttlMinutes = 30)
 * @method static int releaseAllocation(\AIArmada\Inventory\Models\InventoryAllocation $allocation)
 * @method static int release(\Illuminate\Database\Eloquent\Model $model, string $cartId)
 * @method static int releaseAllForCart(string $cartId)
 * @method static array<\AIArmada\Inventory\Models\InventoryMovement> commit(string $cartId, ?string $orderId = null)
 * @method static int extendAllocations(string $cartId, int $minutes)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \AIArmada\Inventory\Models\InventoryAllocation> getAllocationsForCart(string $cartId)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \AIArmada\Inventory\Models\InventoryAllocation> getAllocations(\Illuminate\Database\Eloquent\Model $model, string $cartId)
 * @method static bool hasAvailableInventory(\Illuminate\Database\Eloquent\Model $model, int $quantity)
 * @method static int getTotalAvailable(\Illuminate\Database\Eloquent\Model $model)
 * @method static array{available: bool, issues: array<int, array{model: \Illuminate\Database\Eloquent\Model, requested: int, available: int}>} validateAvailability(array<int, array{model: \Illuminate\Database\Eloquent\Model, quantity: int}> $items)
 * @method static int cleanupExpired()
 * @method static int cleanupExpiredGlobal()
 * @method static \AIArmada\Inventory\Enums\AllocationStrategy getStrategy(\Illuminate\Database\Eloquent\Model $model)
 *
 * @see InventoryAllocationService
 */
final class InventoryAllocation extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InventoryAllocationService::class;
    }
}
