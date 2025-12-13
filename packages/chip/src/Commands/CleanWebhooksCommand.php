<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Webhook;
use Illuminate\Console\Command;
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

        $query = Webhook::query()
            ->where('created_at', '<', $cutoffDate);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No webhooks found matching the criteria.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} webhook(s) older than {$days} days with status '{$status}'.");

        if ($this->option('dry-run')) {
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
}
