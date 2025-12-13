<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Services\MetricsAggregator;
use Illuminate\Console\Command;
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
        // Specific date
        if ($dateOption = $this->option('date')) {
            $date = Carbon::parse($dateOption);
            $this->info("Aggregating metrics for {$date->toDateString()}...");

            $aggregator->aggregateForDate($date);

            $this->info('Done.');

            return self::SUCCESS;
        }

        // Date range (backfill)
        if ($this->option('from') && $this->option('to')) {
            $from = Carbon::parse($this->option('from'));
            $to = Carbon::parse($this->option('to'));

            $this->info("Backfilling metrics from {$from->toDateString()} to {$to->toDateString()}...");

            $days = $aggregator->backfill($from, $to);

            $this->info("Aggregated metrics for {$days} day(s).");

            return self::SUCCESS;
        }

        // Default: aggregate yesterday
        $yesterday = Carbon::yesterday();
        $this->info("Aggregating metrics for {$yesterday->toDateString()} (yesterday)...");

        $aggregator->aggregateForDate($yesterday);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
