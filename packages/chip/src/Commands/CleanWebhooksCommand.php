<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Webhook;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class CleanWebhooksCommand extends Command
{
    protected $signature = 'chip:clean-webhooks
                            {--days=30 : Delete webhooks older than this many days}
                            {--status=processed : Only delete webhooks with this status (processed, failed, all)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean old webhook records from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $status = $this->option('status');
        $cutoffDate = Carbon::now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            return $this->handleAllOwners($cutoffDate, (string) $status, $days, $dryRun);
        }

        return $this->handleForOwner($cutoffDate, (string) $status, $days, $dryRun, OwnerContext::resolve());
    }

    private function handleAllOwners(Carbon $cutoffDate, string $status, int $days, bool $dryRun): int
    {
        $owners = Webhook::query()
            ->withoutOwnerScope()
            ->select(['owner_type', 'owner_id'])
            ->distinct()
            ->get();

        if ($owners->isEmpty()) {
            return $this->handleForOwner($cutoffDate, $status, $days, $dryRun, null);
        }

        $ownerBatches = [];
        $totalCount = 0;

        foreach ($owners as $row) {
            $owner = $this->resolveOwnerFromRow($row);
            $count = $this->buildQuery($cutoffDate, $status, $owner)->count();
            $ownerBatches[] = [
                'owner' => $owner,
                'count' => $count,
            ];
            $totalCount += $count;
        }

        if ($totalCount === 0) {
            $this->info('No webhooks found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} webhook(s) older than {$days} days with status '{$status}'.");

        if ($dryRun) {
            $this->warn('Dry run mode - no webhooks will be deleted.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Are you sure you want to delete {$totalCount} webhook records?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($ownerBatches as $batch) {
            if ($batch['count'] === 0) {
                continue;
            }

            $deleted += $this->buildQuery($cutoffDate, $status, $batch['owner'])->delete();
        }

        $this->info("Successfully deleted {$deleted} webhook record(s).");

        return self::SUCCESS;
    }

    private function handleForOwner(Carbon $cutoffDate, string $status, int $days, bool $dryRun, ?Model $owner): int
    {
        $query = $this->buildQuery($cutoffDate, $status, $owner);
        $count = $query->count();

        if ($count === 0) {
            $this->info('No webhooks found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} webhook(s) older than {$days} days with status '{$status}'.");

        if ($dryRun) {
            $this->warn('Dry run mode - no webhooks will be deleted.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Are you sure you want to delete {$count} webhook records?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $deleted = $query->delete();

        $this->info("Successfully deleted {$deleted} webhook record(s).");

        return self::SUCCESS;
    }

    private function buildQuery(Carbon $cutoffDate, string $status, ?Model $owner): \Illuminate\Database\Eloquent\Builder
    {
        $query = Webhook::query()
            ->forOwner($owner, false)
            ->where('created_at', '<', $cutoffDate);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query;
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
