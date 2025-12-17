<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Console;

use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Console\Command;

final class CleanupExpiredAllocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'inventory:cleanup-allocations
                            {--dry-run : Show what would be cleaned up without actually cleaning}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired inventory allocations';

    public function handle(InventoryAllocationService $allocationService): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode (no changes will be made)');
        }

        $this->info('Cleaning up expired inventory allocations...');

        if ($isDryRun) {
            $allocationsQuery = InventoryAllocation::query()->expired();

            if (InventoryOwnerScope::isEnabled()) {
                InventoryOwnerScope::applyToQueryByLocationRelation($allocationsQuery, 'location');
            }

            $count = $allocationsQuery->count();

            $this->info("Would clean up {$count} expired allocations.");
        } else {
            $count = InventoryOwnerScope::isEnabled()
                ? $allocationService->cleanupExpired()
                : $allocationService->cleanupExpiredGlobal();
            $this->info("Cleaned up {$count} expired allocations.");
        }

        return self::SUCCESS;
    }
}
