<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Console;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Console\Command;

final class CleanupExpiredAllocationsCommand extends Command
{
    protected $signature = 'inventory:cleanup-allocations
                            {--dry-run : Show what would be cleaned up without actually cleaning}';

    protected $description = 'Clean up expired inventory allocations';

    public function handle(InventoryAllocationService $allocationService): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode (no changes will be made)');
        }

        $this->info('Cleaning up expired inventory allocations...');

        $runner = new OwnerBatchRunner(
            InventoryLocation::class,
            ['enabled' => 'inventory.owner.enabled'],
        );

        $runner->run(function () use ($allocationService, $isDryRun): void {
            $this->processScoped($allocationService, $isDryRun);
        });

        return self::SUCCESS;
    }

    private function processScoped(InventoryAllocationService $allocationService, bool $isDryRun): void
    {
        if ($isDryRun) {
            $allocationsQuery = InventoryAllocation::query()->expired();

            if (InventoryOwnerScope::isEnabled()) {
                InventoryOwnerScope::applyToQueryByLocationRelation($allocationsQuery, 'location');
            }

            $count = $allocationsQuery->count();

            $this->info("Would clean up {$count} expired allocations.");

            return;
        }

        $count = InventoryOwnerScope::isEnabled()
            ? $allocationService->cleanupExpired()
            : $allocationService->cleanupExpiredGlobal();

        $this->info("Cleaned up {$count} expired allocations.");
    }
}
