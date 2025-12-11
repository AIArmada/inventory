<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exports;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryMovement;
use Carbon\CarbonImmutable;

/**
 * Export inventory movements to various formats.
 */
final class MovementExport implements ExportableInterface
{
    public function __construct(
        private ?CarbonImmutable $startDate = null,
        private ?CarbonImmutable $endDate = null,
        private ?MovementType $movementType = null,
        private ?string $locationId = null,
    ) {
        $this->startDate ??= CarbonImmutable::now()->subMonth();
        $this->endDate ??= CarbonImmutable::now();
    }

    public function getHeaders(): array
    {
        return [
            'ID',
            'Type',
            'SKU Type',
            'SKU ID',
            'From Location',
            'To Location',
            'Quantity',
            'Reason',
            'Reference',
            'Note',
            'Occurred At',
            'Created At',
        ];
    }

    public function getRows(): iterable
    {
        $query = InventoryMovement::query()
            ->with(['fromLocation:id,name', 'toLocation:id,name'])
            ->whereBetween('occurred_at', [$this->startDate, $this->endDate])
            ->orderBy('occurred_at', 'desc');

        if ($this->movementType !== null) {
            $query->where('type', $this->movementType->value);
        }

        if ($this->locationId !== null) {
            $query->where(function ($q): void {
                $q->where('from_location_id', $this->locationId)
                    ->orWhere('to_location_id', $this->locationId);
            });
        }

        foreach ($query->cursor() as $movement) {
            yield [
                $movement->id,
                $movement->type,
                $movement->inventoryable_type,
                $movement->inventoryable_id,
                $movement->fromLocation?->name ?? '-',
                $movement->toLocation?->name ?? '-',
                $movement->quantity,
                $movement->reason,
                $movement->reference,
                $movement->note,
                $movement->occurred_at->format('Y-m-d H:i:s'),
                $movement->created_at->format('Y-m-d H:i:s'),
            ];
        }
    }

    public function getFilename(): string
    {
        $suffix = $this->movementType !== null ? "-{$this->movementType->value}" : '';

        return 'movements' . $suffix . '-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
