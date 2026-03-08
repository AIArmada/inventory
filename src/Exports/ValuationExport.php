<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exports;

use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;

/**
 * Export valuation snapshots for financial reporting.
 */
final class ValuationExport implements ExportableInterface
{
    public function __construct(
        private ?CarbonImmutable $startDate = null,
        private ?CarbonImmutable $endDate = null,
    ) {
        $this->startDate ??= CarbonImmutable::now()->subMonths(12);
        $this->endDate ??= CarbonImmutable::now();
    }

    public function getHeaders(): array
    {
        return [
            'Snapshot Date',
            'Costing Method',
            'Location',
            'SKU Count',
            'Total Quantity',
            'Total Value',
            'Average Unit Cost',
            'Currency',
            'Variance From Previous',
            'Created At',
        ];
    }

    public function getRows(): iterable
    {
        $query = InventoryValuationSnapshot::query()
            ->with('location:id,name')
            ->whereBetween('snapshot_date', [$this->startDate, $this->endDate])
            ->orderBy('snapshot_date', 'desc');

        if (InventoryOwnerScope::isEnabled()) {
            $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::isCurrentContextGlobalOnly();

            $query->where(function ($builder) use ($includeNullLocation): void {
                InventoryOwnerScope::applyToQueryByLocationRelation($builder, 'location');

                if ($includeNullLocation) {
                    $builder->orWhereNull('location_id');
                }
            });
        }

        foreach ($query->cursor() as $snapshot) {
            yield [
                $snapshot->snapshot_date->format('Y-m-d'),
                $snapshot->costing_method->label(),
                $snapshot->location->name ?? 'All Locations',
                $snapshot->sku_count,
                $snapshot->total_quantity,
                $snapshot->total_value_minor / 100,
                $snapshot->average_unit_cost_minor / 100,
                $snapshot->currency,
                $snapshot->variance_from_previous_minor !== null ? $snapshot->variance_from_previous_minor / 100 : null,
                $snapshot->created_at->format('Y-m-d H:i:s'),
            ];
        }
    }

    public function getFilename(): string
    {
        return 'valuation-' . CarbonImmutable::now()->format('Y-m-d-His');
    }
}
