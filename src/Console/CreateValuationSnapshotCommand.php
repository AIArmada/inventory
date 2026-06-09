<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Console;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\Costing\ValuationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class CreateValuationSnapshotCommand extends Command
{
    protected $signature = 'inventory:create-valuation-snapshot
                            {--method=fifo : The costing method (fifo, weighted_average, standard)}
                            {--location= : The location ID to create snapshot for (null for all locations)}
                            {--date= : The snapshot date (defaults to today)}';

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

        if ($locationId !== null) {
            return $this->createSnapshot($valuationService, $method, $locationId, $date);
        }

        $this->info('Creating valuation snapshots...');
        $this->info("Costing method: {$method->label()}");

        $runner = new OwnerBatchRunner(
            InventoryLocation::class,
            ['enabled' => 'inventory.owner.enabled'],
        );

        $result = $runner->forEach(function () use ($valuationService, $method, $date): int {
            return $this->createSnapshot($valuationService, $method, null, $date);
        });

        $failed = $result->contains(static fn (int $r): bool => $r !== self::SUCCESS);

        return $failed ? self::FAILURE : self::SUCCESS;
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
            $this->info('Valuation snapshot created successfully!');
            $this->newLine();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Snapshot ID', $snapshot->id],
                    ['Date', $snapshot->snapshot_date->format('Y-m-d')],
                    ['Costing Method', $snapshot->costing_method->label()],
                    ['SKU Count', number_format($snapshot->sku_count)],
                    ['Total Quantity', number_format($snapshot->total_quantity)],
                    ['Total Value', MoneyFormatter::formatMinorWithCode($snapshot->total_value_minor, $snapshot->currency)],
                    ['Average Unit Cost', MoneyFormatter::formatMinorWithCode($snapshot->average_unit_cost_minor, $snapshot->currency, 4)],
                ]
            );

            if ($snapshot->variance_from_previous_minor !== null) {
                $variancePercent = $snapshot->variancePercentage();
                $sign = $snapshot->isPositiveVariance() ? '+' : '';
                $this->info(sprintf(
                    'Variance from previous: %s%s (%s%s%%)',
                    $sign,
                    MoneyFormatter::formatMinorWithCode($snapshot->variance_from_previous_minor, $snapshot->currency),
                    $sign,
                    number_format($variancePercent ?? 0, 2)
                ));
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error("Failed to create valuation snapshot: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
