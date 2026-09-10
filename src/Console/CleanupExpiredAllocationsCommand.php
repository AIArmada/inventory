<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Console;

use AIArmada\CommerceSupport\Support\OwnerBatchRunner;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Services\Stock\CheckoutReservationService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Console\Command;

final class CleanupExpiredAllocationsCommand extends Command
{
    protected $signature = 'inventory:cleanup-allocations
                            {--dry-run : Show what would be cleaned up without actually cleaning}';

    protected $description = 'Clean up expired inventory allocations';

    public function handle(
        InventoryAllocationService $allocationService,
        CheckoutReservationService $reservationService,
    ): int {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Running in dry-run mode (no changes will be made)');
        }

        $this->info('Cleaning up expired inventory allocations...');

        $runner = new OwnerBatchRunner(
            InventoryLocation::class,
            ['enabled' => 'inventory.owner.enabled'],
        );

        $runner->run(function () use ($allocationService, $reservationService, $isDryRun): void {
            $this->processScoped($allocationService, $reservationService, $isDryRun);
        });

        return self::SUCCESS;
    }

    private function processScoped(
        InventoryAllocationService $allocationService,
        CheckoutReservationService $reservationService,
        bool $isDryRun,
    ): void {
        if ($isDryRun) {
            $allocationsQuery = InventoryOwnerScope::applyToLocationQuery(
                InventoryAllocation::query()->expired()
            );

            $count = $allocationsQuery->count();

            $this->info("Would clean up {$count} expired allocations.");
            $reservationCount = $this->reservationsToCleanupCount();
            $this->info("Would clean up {$reservationCount} expired reservations.");

            return;
        }

        $count = InventoryOwnerScope::isEnabled()
            ? $allocationService->cleanupExpired()
            : $allocationService->cleanupExpiredGlobal();

        $this->info("Cleaned up {$count} expired allocations.");
        $reservationCount = $reservationService->cleanupExpiredReservations();
        $this->info("Cleaned up {$reservationCount} expired reservations.");
    }

    private function reservationsToCleanupCount(): int
    {
        $now = now();
        $cutoff = $now->copy()->subMinutes(max(0, (int) config('inventory.cleanup.keep_expired_for_minutes', 0)));

        return InventoryOwnerScope::applyToLocationQuery(InventoryReservation::query())
            ->where(function ($query) use ($now, $cutoff): void {
                $query
                    ->where(function ($reserved) use ($now): void {
                        $reserved
                            ->where('status', InventoryReservation::STATE_RESERVED)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '<=', $now);
                    })
                    ->orWhere(function ($terminal) use ($cutoff): void {
                        $terminal
                            ->whereIn('status', [
                                InventoryReservation::STATE_COMMITTED,
                                InventoryReservation::STATE_RELEASED,
                                InventoryReservation::STATE_EXPIRED,
                            ])
                            ->where('updated_at', '<=', $cutoff);
                    });
            })
            ->count();
    }
}
