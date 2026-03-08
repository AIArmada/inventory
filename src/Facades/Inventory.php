<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Facades;

use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \AIArmada\Inventory\Models\InventoryMovement receive(\Illuminate\Database\Eloquent\Model $model, string $locationId, int $quantity, ?string $reason = null, ?string $note = null, ?string $userId = null)
 * @method static \AIArmada\Inventory\Models\InventoryMovement ship(\Illuminate\Database\Eloquent\Model $model, string $locationId, int $quantity, ?string $reason = null, ?string $reference = null, ?string $note = null, ?string $userId = null)
 * @method static \AIArmada\Inventory\Models\InventoryMovement transfer(\Illuminate\Database\Eloquent\Model $model, string $fromLocationId, string $toLocationId, int $quantity, ?string $note = null, ?string $userId = null)
 * @method static \AIArmada\Inventory\Models\InventoryMovement adjust(\Illuminate\Database\Eloquent\Model $model, string $locationId, int $newQuantity, ?string $reason = null, ?string $note = null, ?string $userId = null)
 * @method static array<string, int> getAvailability(\Illuminate\Database\Eloquent\Model $model)
 * @method static int getTotalAvailable(\Illuminate\Database\Eloquent\Model $model)
 * @method static int getTotalOnHand(\Illuminate\Database\Eloquent\Model $model)
 * @method static bool hasInventory(\Illuminate\Database\Eloquent\Model $model, int $quantity)
 * @method static \AIArmada\Inventory\Models\InventoryLevel|null getLevel(\Illuminate\Database\Eloquent\Model $model, string $locationId)
 * @method static \AIArmada\Inventory\Models\InventoryLevel getOrCreateLevel(\Illuminate\Database\Eloquent\Model $model, string $locationId)
 * @method static \Illuminate\Database\Eloquent\Collection<int, \AIArmada\Inventory\Models\InventoryMovement> getMovementHistory(\Illuminate\Database\Eloquent\Model $model, int $limit = 50)
 * @method static \AIArmada\Inventory\Models\InventoryMovement receiveAtDefault(\Illuminate\Database\Eloquent\Model $model, int $quantity, ?string $reason = null, ?string $note = null, ?string $userId = null)
 * @method static \AIArmada\Inventory\Models\InventoryMovement shipFromDefault(\Illuminate\Database\Eloquent\Model $model, int $quantity, ?string $reason = null, ?string $reference = null, ?string $note = null, ?string $userId = null)
 *
 * @see InventoryService
 */
final class Inventory extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return InventoryService::class;
    }
}
