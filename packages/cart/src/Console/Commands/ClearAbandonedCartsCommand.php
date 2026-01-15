<?php

declare(strict_types=1);

namespace AIArmada\Cart\Console\Commands;

use AIArmada\Cart\Support\CartOwnerScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

final class ClearAbandonedCartsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cart:clear-abandoned 
                          {--days=7 : Number of days after which cart is considered abandoned}
                          {--expired : Only delete carts that have passed their expires_at timestamp}
                          {--dry-run : Show what would be deleted without actually deleting}
                          {--batch-size=1000 : Number of records to process in each batch}';

    /**
     * The console command description.
     */
    protected $description = 'Clear abandoned shopping carts older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $useExpired = $this->option('expired');
        $dryRun = $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $table = config('cart.database.table', 'carts');
        $now = now();

        if ($useExpired) {
            info('Clearing expired carts (using expires_at column)');
        } else {
            $cutoffDate = $now->copy()->subDays($days);
            info("Clearing carts abandoned before: {$cutoffDate->format('Y-m-d H:i:s')}");
        }

        if ((bool) config('cart.owner.enabled', false)) {
            $owner = OwnerContext::resolve();
            if ($owner === null) {
                return $this->handleAllOwners(
                    table: $table,
                    useExpired: $useExpired,
                    days: $days,
                    now: $now,
                    dryRun: (bool) $dryRun,
                    batchSize: $batchSize
                );
            }

            return $this->handleForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: (bool) $dryRun,
                batchSize: $batchSize,
                ownerType: $owner->getMorphClass(),
                ownerId: $owner->getKey(),
            );
        }

        return $this->handleForOwner(
            table: $table,
            useExpired: $useExpired,
            days: $days,
            now: $now,
            dryRun: (bool) $dryRun,
            batchSize: $batchSize,
            ownerType: null,
            ownerId: null,
        );
    }

    private function handleAllOwners(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        bool $dryRun,
        int $batchSize,
    ): int {
        $owners = DB::table($table)->select(['owner_type', 'owner_id'])->distinct()->get();

        if ($owners->isEmpty()) {
            return $this->handleForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: $dryRun,
                batchSize: $batchSize,
                ownerType: null,
                ownerId: null,
            );
        }

        $ownerBatches = [];
        $totalCount = 0;

        foreach ($owners as $row) {
            $ownerType = $this->normalizeOwnerValue($row->owner_type ?? null);
            $ownerId = $this->normalizeOwnerValue($row->owner_id ?? null);

            $count = $this->countForOwner($table, $useExpired, $days, $now, $ownerType, $ownerId);
            $ownerBatches[] = [
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'count' => $count,
            ];
            $totalCount += $count;
        }

        if ($dryRun) {
            warning('DRY RUN MODE - No data will be deleted');
        }

        if ($totalCount === 0) {
            info('No abandoned carts found.');

            return self::SUCCESS;
        }

        info("Found {$totalCount} abandoned carts to clear.");

        if ($dryRun) {
            $deletedCount = 0;
            foreach ($ownerBatches as $batch) {
                if ($batch['count'] === 0) {
                    continue;
                }

                $deletedCount += $this->deleteForOwner(
                    table: $table,
                    useExpired: $useExpired,
                    days: $days,
                    now: $now,
                    dryRun: true,
                    batchSize: $batchSize,
                    ownerType: $batch['owner_type'],
                    ownerId: $batch['owner_id'],
                );
            }

            info("Would delete {$deletedCount} abandoned carts.");

            return self::SUCCESS;
        }

        $confirmed = confirm('Are you sure you want to delete these carts?');
        if (! $confirmed) {
            info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deletedCount = 0;

        foreach ($ownerBatches as $batch) {
            if ($batch['count'] === 0) {
                continue;
            }

            $deletedCount += $this->deleteForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: false,
                batchSize: $batchSize,
                ownerType: $batch['owner_type'],
                ownerId: $batch['owner_id'],
            );
        }

        info("Successfully deleted {$deletedCount} abandoned carts.");

        return self::SUCCESS;
    }

    private function handleForOwner(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        bool $dryRun,
        int $batchSize,
        ?string $ownerType,
        string | int | null $ownerId,
    ): int {
        if ($dryRun) {
            warning('DRY RUN MODE - No data will be deleted');
        }

        $totalCount = $this->countForOwner($table, $useExpired, $days, $now, $ownerType, $ownerId);

        if ($totalCount === 0) {
            info('No abandoned carts found.');

            return self::SUCCESS;
        }

        info("Found {$totalCount} abandoned carts to clear.");

        if ($dryRun) {
            $deletedCount = $this->deleteForOwner(
                table: $table,
                useExpired: $useExpired,
                days: $days,
                now: $now,
                dryRun: true,
                batchSize: $batchSize,
                ownerType: $ownerType,
                ownerId: $ownerId,
            );

            info("Would delete {$deletedCount} abandoned carts.");

            return self::SUCCESS;
        }

        $confirmed = confirm('Are you sure you want to delete these carts?');
        if (! $confirmed) {
            info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deletedCount = $this->deleteForOwner(
            table: $table,
            useExpired: $useExpired,
            days: $days,
            now: $now,
            dryRun: false,
            batchSize: $batchSize,
            ownerType: $ownerType,
            ownerId: $ownerId,
        );

        info("Successfully deleted {$deletedCount} abandoned carts.");

        return self::SUCCESS;
    }

    private function countForOwner(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        ?string $ownerType,
        string | int | null $ownerId,
    ): int {
        $query = $this->baseQuery($table, $useExpired, $days, $now);
        CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);

        return $query->count();
    }

    private function deleteForOwner(
        string $table,
        bool $useExpired,
        int $days,
        CarbonInterface $now,
        bool $dryRun,
        int $batchSize,
        ?string $ownerType,
        string | int | null $ownerId,
    ): int {
        $query = $this->baseQuery($table, $useExpired, $days, $now);
        CartOwnerScope::applyForOwner($query, $ownerType, $ownerId);

        $deletedCount = 0;

        progress(
            label: $dryRun ? 'Simulating deletion...' : 'Deleting carts...',
            steps: $query->clone()->pluck('id')->chunk($batchSize),
            callback: function ($chunk) use (&$deletedCount, $dryRun, $table, $ownerType, $ownerId): void {
                if (! $dryRun) {
                    // Defense-in-depth: re-apply owner scope on delete to prevent cross-tenant deletion
                    $deleteQuery = DB::table($table)->whereIn('id', $chunk->toArray());
                    CartOwnerScope::applyForOwner($deleteQuery, $ownerType, $ownerId);
                    $deleted = $deleteQuery->delete();
                    $deletedCount += $deleted;
                } else {
                    $deletedCount += $chunk->count();
                }
            }
        );

        return $deletedCount;
    }

    private function baseQuery(string $table, bool $useExpired, int $days, CarbonInterface $now): Builder
    {
        if ($useExpired) {
            return DB::table($table)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now);
        }

        $cutoffDate = $now->copy()->subDays($days);

        return DB::table($table)
            ->where('updated_at', '<', $cutoffDate);
    }

    private function normalizeOwnerValue(string | int | null $value): string | int | null
    {
        if (is_string($value) && $value === '') {
            return null;
        }

        return $value;
    }
}
