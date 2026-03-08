<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Health;

use AIArmada\CommerceSupport\Health\CommerceHealthCheck;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Spatie\Health\Checks\Result;

/**
 * Health check for low stock inventory levels.
 */
class LowStockCheck extends CommerceHealthCheck
{
    public ?string $name = 'Low Stock Alert';

    /**
     * The threshold for low stock warning.
     */
    protected int $threshold = 10;

    /**
     * Whether to fail on low stock.
     */
    protected bool $failOnLowStock = false;

    /**
     * Set the low stock threshold.
     */
    public function threshold(int $threshold): self
    {
        $this->threshold = $threshold;

        return $this;
    }

    /**
     * Configure the check to fail instead of warn on low stock.
     */
    public function failOnLowStock(bool $fail = true): self
    {
        $this->failOnLowStock = $fail;

        return $this;
    }

    /**
     * Perform the health check.
     */
    protected function performCheck(): Result
    {
        $lowStockQuery = InventoryLevel::query()
            ->where('quantity_on_hand', '<=', $this->threshold)
            ->where('quantity_on_hand', '>', 0);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($lowStockQuery, 'location');
        }

        $lowStockCount = $lowStockQuery->count();

        $outOfStockQuery = InventoryLevel::query()
            ->where('quantity_on_hand', '<=', 0);

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($outOfStockQuery, 'location');
        }

        $outOfStockCount = $outOfStockQuery->count();

        if ($outOfStockCount > 0) {
            $message = "{$outOfStockCount} items are out of stock";

            if ($lowStockCount > 0) {
                $message .= ", {$lowStockCount} items have low stock";
            }

            return $this->failOnLowStock
                ? $this->failure($message, [
                    'out_of_stock' => $outOfStockCount,
                    'low_stock' => $lowStockCount,
                    'threshold' => $this->threshold,
                ])
                : $this->warning($message, [
                    'out_of_stock' => $outOfStockCount,
                    'low_stock' => $lowStockCount,
                    'threshold' => $this->threshold,
                ]);
        }

        if ($lowStockCount > 0) {
            return $this->warning("{$lowStockCount} items have low stock (≤{$this->threshold})", [
                'low_stock' => $lowStockCount,
                'threshold' => $this->threshold,
            ]);
        }

        return $this->success('All inventory levels are healthy', [
            'threshold' => $this->threshold,
        ]);
    }
}
