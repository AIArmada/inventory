<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Console;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleParser;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
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

        if (InventoryOwnerScope::isEnabled() && OwnerContext::resolve() === null) {
            $owners = InventoryLocation::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            $ownerTupleColumns = OwnerTupleColumns::forModelClass(InventoryLocation::class);

            if ($owners->isEmpty()) {
                $this->processScoped($allocationService, (bool) $isDryRun);

                return self::SUCCESS;
            }

            foreach ($owners as $row) {
                $ownerTuple = OwnerTupleParser::fromRow($row, $ownerTupleColumns);
                $owner = $ownerTuple->toOwnerModel();

                OwnerContext::withOwner($owner, function () use ($allocationService, $isDryRun): void {
                    $this->processScoped($allocationService, (bool) $isDryRun);
                });
            }

            return self::SUCCESS;
        }

        $this->processScoped($allocationService, (bool) $isDryRun);

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
