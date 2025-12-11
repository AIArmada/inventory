<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exports;

use AIArmada\Inventory\Models\InventoryBatch;
use Carbon\CarbonImmutable;

/**
 * Export batch/lot information.
 */
final class BatchExport implements ExportableInterface
{
    public function __construct(
        private ?string $status = null,
        private bool $expiringOnly = false,
        private int $expiringWithinDays = 30,
    ) {}

    public function getHeaders(): array
    {
        return [
            'Batch Number',
            'SKU Type',
            'SKU ID',
            'Location',
            'Quantity',
            'Status',
            'Unit Cost',
            'Total Value',
            'Manufactured Date',
            'Expiry Date',
            'Days Until Expiry',
            'Supplier',
            'Purchase Order',
            'Notes',
            'Created At',
        ];
    }

    public function getRows(): iterable
    {
        $query = InventoryBatch::query()
            ->with('location:id,name');

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->expiringOnly) {
            $expiryDate = CarbonImmutable::now()->addDays($this->expiringWithinDays);
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<=', $expiryDate)
                ->where('expires_at', '>', now());
        }

        foreach ($query->cursor() as $batch) {
            $daysUntilExpiry = $batch->expires_at !== null
                ? CarbonImmutable::now()->diffInDays($batch->expires_at, false)
                : null;

            yield [
                $batch->batch_number,
                $batch->inventoryable_type,
                $batch->inventoryable_id,
                $batch->location->name ?? 'Unknown',
                $batch->quantity_on_hand,
                $batch->status,
                $batch->unit_cost_minor !== null ? $batch->unit_cost_minor / 100 : 0,
                ($batch->quantity_on_hand * ($batch->unit_cost_minor ?? 0)) / 100,
                $batch->manufactured_at?->format('Y-m-d'),
                $batch->expires_at?->format('Y-m-d'),
                $daysUntilExpiry,
                $batch->supplier_id,
                $batch->purchase_order_number,
                $batch->metadata['notes'] ?? null,
                $batch->created_at->format('Y-m-d H:i:s'),
            ];
        }
    }

    public function getFilename(): string
    {
        $suffix = $this->expiringOnly ? '-expiring' : '';

        return 'batches' . $suffix . '-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
