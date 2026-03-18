<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\ValuationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class CreateValuationSnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:create-valuation-snapshot
                            {--method=fifo : The costing method (fifo, weighted_average, standard)}
                            {--location= : The location ID to create snapshot for (null for all locations)}
                            {--date= : The snapshot date (defaults to today)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a valuation snapshot for inventory reporting';

    public function handle(ValuationService $valuationService): int
    {
        $methodValue = $this->option('method') ?? 'fifo';
        $method = CostingMethod::tryFrom($methodValue);

        if ($method === null) {
            $this->error("Invalid costing method: {$methodValue}");
            $this->info('Valid methods: fifo, weighted_average, standard');

            return self::FAILURE;
        }

        $locationId = $this->option('location');
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : null;

        $this->info('Creating valuation snapshot for date: ' . ($date?->format('Y-m-d') ?? 'today'));
        $this->info("Costing method: {$method->label()}");

        if ($locationId !== null) {
            $this->info("Location: {$locationId}");
        } else {
            $this->info('Location: All locations');
        }

        if (InventoryOwnerScope::isEnabled() && OwnerContext::resolve() === null) {
            if ($locationId !== null) {
                $location = InventoryLocation::query()
                    ->withoutOwnerScope()
                    ->whereKey($locationId)
                    ->first();

                if ($location === null) {
                    $this->error('Unable to resolve the specified location.');

                    return self::FAILURE;
                }

                $owner = $location->owner;

                return OwnerContext::withOwner($owner, fn (): int => $this->createSnapshot($valuationService, $method, $locationId, $date));
            }

            $owners = InventoryLocation::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            if ($owners->isEmpty()) {
                return $this->createSnapshot($valuationService, $method, $locationId, $date);
            }

            $failed = false;

            foreach ($owners as $row) {
                $owner = $this->resolveOwnerFromRow($row);
                $result = OwnerContext::withOwner($owner, fn (): int => $this->createSnapshot($valuationService, $method, $locationId, $date));

                if ($result !== self::SUCCESS) {
                    $failed = true;
                }
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        return $this->createSnapshot($valuationService, $method, $locationId, $date);
    }

    private function createSnapshot(
        ValuationService $valuationService,
        CostingMethod $method,
        ?string $locationId,
        ?Carbon $date,
    ): int {
        try {
            $snapshot = $valuationService->createSnapshot($method, $locationId, $date);

            $this->newLine();
            $this->info('✅ Valuation snapshot created successfully!');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Snapshot ID', $snapshot->id],
                    ['Date', $snapshot->snapshot_date->format('Y-m-d')],
                    ['Costing Method', $snapshot->costing_method->label()],
                    ['SKU Count', number_format($snapshot->sku_count)],
                    ['Total Quantity', number_format($snapshot->total_quantity)],
                    ['Total Value', number_format($snapshot->total_value_minor / 100, 2) . ' ' . $snapshot->currency],
                    ['Average Unit Cost', number_format($snapshot->average_unit_cost_minor / 100, 4) . ' ' . $snapshot->currency],
                ]
            );

            if ($snapshot->variance_from_previous_minor !== null) {
                $variancePercent = $snapshot->variancePercentage();
                $sign = $snapshot->isPositiveVariance() ? '+' : '';
                $this->info("Variance from previous: {$sign}" . number_format($snapshot->variance_from_previous_minor / 100, 2) . " ({$sign}" . number_format($variancePercent ?? 0, 2) . '%)');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create valuation snapshot: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function resolveOwnerFromRow(object $row): ?Model
    {
        $ownerType = $row->owner_type ?? null;
        $ownerId = $row->owner_id ?? null;

        return OwnerContext::fromTypeAndId(
            is_string($ownerType) ? $ownerType : null,
            is_string($ownerId) || is_int($ownerId) ? $ownerId : null
        );
    }
}
