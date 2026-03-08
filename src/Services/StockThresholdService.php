<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\MaxStockExceeded;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Events\SafetyStockBreached;
use AIArmada\Inventory\Events\StockRestored;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

final class StockThresholdService
{
    /**
     * Evaluate and update the alert status for an inventory level.
     */
    public function evaluateThresholds(InventoryLevel $level): AlertStatus
    {
        $previousStatus = $level->alert_status !== null
            ? AlertStatus::from($level->alert_status)
            : AlertStatus::None;

        $newStatus = $this->calculateStatus($level);

        if ($newStatus !== $previousStatus) {
            $this->updateAlertStatus($level, $newStatus);
            $this->dispatchStatusEvents($level, $previousStatus, $newStatus);
        }

        return $newStatus;
    }

    /**
     * Calculate the current alert status based on stock levels.
     */
    public function calculateStatus(InventoryLevel $level): AlertStatus
    {
        $available = $level->available;
        $onHand = $level->quantity_on_hand;

        // Check out of stock first (most critical)
        if ($available <= 0) {
            return AlertStatus::OutOfStock;
        }

        // Check safety stock breach
        if ($level->safety_stock !== null && $available <= $level->safety_stock) {
            return AlertStatus::SafetyBreached;
        }

        // Check low stock (below reorder point)
        $reorderPoint = $level->reorder_point ?? config('inventory.default_reorder_point', 10);

        if ($available <= $reorderPoint) {
            return AlertStatus::LowStock;
        }

        // Check over stock
        if ($level->max_stock !== null && $onHand > $level->max_stock) {
            return AlertStatus::OverStock;
        }

        return AlertStatus::None;
    }

    /**
     * Check if an inventory level needs attention.
     */
    public function needsAttention(InventoryLevel $level): bool
    {
        $status = $this->calculateStatus($level);

        return $status !== AlertStatus::None && $status !== AlertStatus::OverStock;
    }

    /**
     * Get all levels that need reordering.
     *
     * @return Collection<int, InventoryLevel>
     */
    public function getLevelsNeedingReorder(): Collection
    {
        $query = InventoryLevel::query()
            ->whereIn('alert_status', [
                AlertStatus::LowStock->value,
                AlertStatus::SafetyBreached->value,
                AlertStatus::OutOfStock->value,
            ])
            ->with('location');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        return $query->get();
    }

    /**
     * Get suggested reorder quantity for a level.
     */
    public function getSuggestedReorderQuantity(InventoryLevel $level): int
    {
        // Default to ordering up to max stock, or 2x reorder point if no max
        $targetStock = $level->max_stock
            ?? ($level->reorder_point ?? config('inventory.default_reorder_point', 10)) * 2;

        $currentStock = $level->quantity_on_hand;

        return max(0, $targetStock - $currentStock);
    }

    /**
     * Check if receiving a quantity would exceed max stock.
     */
    public function wouldExceedMaxStock(InventoryLevel $level, int $quantity): bool
    {
        if ($level->max_stock === null) {
            return false;
        }

        return ($level->quantity_on_hand + $quantity) > $level->max_stock;
    }

    /**
     * Get the quantity that can be received without exceeding max stock.
     */
    public function getReceivableQuantity(InventoryLevel $level, int $desired): int
    {
        if ($level->max_stock === null) {
            return $desired;
        }

        $space = max(0, $level->max_stock - $level->quantity_on_hand);

        return min($desired, $space);
    }

    /**
     * Bulk evaluate thresholds for multiple levels.
     *
     * @param  iterable<InventoryLevel>  $levels
     * @return array<string, AlertStatus>
     */
    public function bulkEvaluate(iterable $levels): array
    {
        $results = [];

        foreach ($levels as $level) {
            $results[$level->id] = $this->evaluateThresholds($level);
        }

        return $results;
    }

    /**
     * Get threshold summary for a level.
     *
     * @return array{
     *     available: int,
     *     on_hand: int,
     *     reserved: int,
     *     reorder_point: int|null,
     *     safety_stock: int|null,
     *     max_stock: int|null,
     *     status: AlertStatus,
     *     suggested_reorder: int
     * }
     */
    public function getThresholdSummary(InventoryLevel $level): array
    {
        return [
            'available' => $level->available,
            'on_hand' => $level->quantity_on_hand,
            'reserved' => $level->quantity_reserved,
            'reorder_point' => $level->reorder_point,
            'safety_stock' => $level->safety_stock,
            'max_stock' => $level->max_stock,
            'status' => $this->calculateStatus($level),
            'suggested_reorder' => $this->getSuggestedReorderQuantity($level),
        ];
    }

    /**
     * Update the alert status on the level.
     */
    private function updateAlertStatus(InventoryLevel $level, AlertStatus $status): void
    {
        $level->update([
            'alert_status' => $status->value,
            'last_alert_at' => now(),
        ]);
    }

    /**
     * Dispatch events based on status change.
     */
    private function dispatchStatusEvents(
        InventoryLevel $level,
        AlertStatus $previousStatus,
        AlertStatus $newStatus
    ): void {
        $inventoryable = $level->inventoryable;

        if (! $inventoryable instanceof Model) {
            return;
        }

        // Stock restored event (moving from critical to normal)
        if ($this->isRestoredStatus($previousStatus, $newStatus)) {
            Event::dispatch(new StockRestored($inventoryable, $level, $previousStatus));

            return;
        }

        // Dispatch specific status events
        match ($newStatus) {
            AlertStatus::OutOfStock => Event::dispatch(new OutOfInventory($inventoryable, $level)),
            AlertStatus::SafetyBreached => Event::dispatch(new SafetyStockBreached($inventoryable, $level, $previousStatus)),
            AlertStatus::LowStock => Event::dispatch(new LowInventoryDetected($inventoryable, $level)),
            AlertStatus::OverStock => Event::dispatch(new MaxStockExceeded($inventoryable, $level)),
            default => null,
        };
    }

    /**
     * Check if the status change represents a restoration.
     */
    private function isRestoredStatus(AlertStatus $previous, AlertStatus $new): bool
    {
        $criticalStatuses = [AlertStatus::OutOfStock, AlertStatus::SafetyBreached, AlertStatus::LowStock];

        return in_array($previous, $criticalStatuses, true)
            && ($new === AlertStatus::None || $new === AlertStatus::OverStock);
    }
}
