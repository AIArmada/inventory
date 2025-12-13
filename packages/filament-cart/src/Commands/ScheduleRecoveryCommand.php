<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\FilamentCart\Models\RecoveryCampaign;
use AIArmada\FilamentCart\Services\RecoveryScheduler;
use Illuminate\Console\Command;

class ScheduleRecoveryCommand extends Command
{
    protected $signature = 'cart:schedule-recovery
                            {--campaign= : Specific campaign ID to process}
                            {--dry-run : Show what would be scheduled without actually scheduling}';

    protected $description = 'Schedule recovery attempts for eligible abandoned carts';

    public function handle(RecoveryScheduler $scheduler): int
    {
        $campaignId = $this->option('campaign');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in dry-run mode. No changes will be made.');
        }

        $query = RecoveryCampaign::query()
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });

        if ($campaignId) {
            $query->where('id', $campaignId);
        }

        $campaigns = $query->get();

        if ($campaigns->isEmpty()) {
            $this->info('No active campaigns found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$campaigns->count()} campaign(s)...");
        $totalScheduled = 0;

        foreach ($campaigns as $campaign) {
            $this->line("  Campaign: {$campaign->name}");

            if ($dryRun) {
                $this->line('    [DRY-RUN] Would process campaign');

                continue;
            }

            $scheduled = $scheduler->scheduleForCampaign($campaign);
            $totalScheduled += $scheduled;

            $this->line("    Scheduled: {$scheduled} attempts");
        }

        $this->newLine();

        if (! $dryRun) {
            $this->info("Total scheduled: {$totalScheduled} recovery attempts");
        }

        return self::SUCCESS;
    }
}
