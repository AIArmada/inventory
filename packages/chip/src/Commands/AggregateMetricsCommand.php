<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Purchase;
use AIArmada\Chip\Services\MetricsAggregator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class AggregateMetricsCommand extends Command
{
    protected $signature = 'chip:aggregate-metrics
                            {--date= : Specific date to aggregate (YYYY-MM-DD)}
                            {--from= : Start date for backfill (YYYY-MM-DD)}
                            {--to= : End date for backfill (YYYY-MM-DD)}';

    protected $description = 'Aggregate purchase metrics into daily summaries';

    public function handle(MetricsAggregator $aggregator): int
    {
        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owners = Purchase::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            if ($owners->isEmpty()) {
                $this->runAggregation($aggregator);

                return self::SUCCESS;
            }

            foreach ($owners as $row) {
                $owner = $this->resolveOwnerFromRow($row);

                OwnerContext::withOwner($owner, function () use ($aggregator): void {
                    $this->runAggregation($aggregator);
                });
            }

            return self::SUCCESS;
        }

        $this->runAggregation($aggregator);

        return self::SUCCESS;
    }

    private function runAggregation(MetricsAggregator $aggregator): void
    {
        // Specific date
        if ($dateOption = $this->option('date')) {
            $date = Carbon::parse($dateOption);
            $this->info("Aggregating metrics for {$date->toDateString()}...");

            $aggregator->aggregateForDate($date);

            $this->info('Done.');

            return;
        }

        // Date range (backfill)
        if ($this->option('from') && $this->option('to')) {
            $from = Carbon::parse($this->option('from'));
            $to = Carbon::parse($this->option('to'));

            $this->info("Backfilling metrics from {$from->toDateString()} to {$to->toDateString()}...");

            $days = $aggregator->backfill($from, $to);

            $this->info("Aggregated metrics for {$days} day(s).");

            return;
        }

        // Default: aggregate yesterday
        $yesterday = Carbon::yesterday();
        $this->info("Aggregating metrics for {$yesterday->toDateString()} (yesterday)...");

        $aggregator->aggregateForDate($yesterday);

        $this->info('Done.');
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
