<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Adjust inventory to a specific quantity at a location.
 */
final class AdjustInventory
{
    use AsAction;

    public function __construct(
        private readonly CheckLowInventory $checkLowInventory,
    ) {}

    /**
     * Adjust inventory to a specific quantity at a location.
     */
    public function handle(
        Model $model,
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($model, $locationId, $newQuantity, $reason, $note, $userId): InventoryMovement {
            $level = $this->getOrCreateLevel($model, $locationId);

            $previousQuantity = $level->quantity_on_hand;
            $difference = $newQuantity - $previousQuantity;

            $level->quantity_on_hand = $newQuantity;
            $level->save();

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'to_location_id' => $locationId,
                'type' => MovementType::Adjustment->value,
                'quantity' => $difference,
                'reason' => $reason,
                'note' => $note,
                'user_id' => $userId,
                'occurred_at' => now(),
            ]);

            Event::dispatch(new InventoryAdjusted($model, $level, $movement, $previousQuantity, $newQuantity));

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
