<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Receive inventory at a location.
 */
final class ReceiveInventory
{
    use AsAction;

    public function __construct(
        private readonly CheckLowInventory $checkLowInventory,
    ) {}

    /**
     * Receive inventory at a location.
     */
    public function handle(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $note, $userId): InventoryMovement {
            $level = $this->getOrCreateLevel($model, $locationId);

            $previousQuantity = $level->quantity_on_hand;
            $level->quantity_on_hand += $quantity;
            $level->save();

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'to_location_id' => $locationId,
                'type' => MovementType::Receipt->value,
                'quantity' => $quantity,
                'reason' => $reason,
                'note' => $note,
                'user_id' => $userId,
                'occurred_at' => now(),
            ]);

            Event::dispatch(new InventoryReceived($model, $level, $movement));

            $this->checkLowInventory->handle($model, $level);

            return $movement;
        });
    }

    private function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        return InventoryLevel::firstOrCreate(
            [
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'location_id' => $locationId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'reorder_point' => config('inventory.default_reorder_point', 10),
                'reorder_quantity' => config('inventory.default_reorder_quantity', 50),
            ]
        );
    }
}
