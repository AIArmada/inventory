<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exports;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Export stock levels to various formats.
 */
final class StockLevelExport implements ExportableInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private array $filters = [],
    ) {}

    public function getHeaders(): array
    {
        return [
            'SKU Type',
            'SKU ID',
            'Location',
            'Quantity On Hand',
            'Reserved',
            'Available',
            'Safety Stock',
            'Max Stock',
            'Reorder Point',
            'Status',
            'Last Updated',
        ];
    }

    public function getRows(): iterable
    {
        $query = InventoryLevel::query()
            ->with('location:id,name');

        if (InventoryOwnerScope::isEnabled()) {
            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');
        }

        if (isset($this->filters['location_id'])) {
            $locationId = (string) $this->filters['location_id'];

            if (InventoryOwnerScope::isEnabled()) {
                $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                    ->whereKey($locationId)
                    ->exists();

                if (! $isAllowed) {
                    throw new InvalidArgumentException('Invalid location for current owner');
                }
            }

            $query->where('location_id', $locationId);
        }

        if (isset($this->filters['low_stock_only']) && $this->filters['low_stock_only']) {
            $query->lowStock();
        }

        if (isset($this->filters['out_of_stock_only']) && $this->filters['out_of_stock_only']) {
            $query->where('quantity_on_hand', '<=', 0);
        }

        foreach ($query->cursor() as $level) {
            $available = $level->quantity_on_hand - $level->quantity_reserved;
            $status = match (true) {
                $level->quantity_on_hand <= 0 => 'Out of Stock',
                $level->isLowStock() => 'Low Stock',
                $level->needsReorder() => 'Reorder Needed',
                default => 'In Stock',
            };

            yield [
                $level->inventoryable_type,
                $level->inventoryable_id,
                $level->location->name ?? 'Unknown',
                $level->quantity_on_hand,
                $level->quantity_reserved,
                $available,
                $level->safety_stock ?? 0,
                $level->max_stock ?? 0,
                $level->reorder_point ?? 0,
                $status,
                $level->updated_at->format('Y-m-d H:i:s'),
            ];
        }
    }

    public function getFilename(): string
    {
        return 'stock-levels-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
