<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\FilamentCart\Services\MetricsAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateMetricsCommand extends Command
{
    protected $signature = 'cart:aggregate-metrics
                            {--date= : Specific date to aggregate (Y-m-d)}
                            {--from= : Start date for range (Y-m-d)}
                            {--to= : End date for range (Y-m-d)}
                            {--days=1 : Number of days to backfill from today}
                            {--segment=* : Optional segments to aggregate}';

    protected $description = 'Aggregate cart metrics for analytics';

    public function handle(MetricsAggregator $aggregator): int
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');
        $days = (int) $this->option('days');
        $segments = $this->option('segment');

        if ($date) {
            $this->aggregateSingleDate($aggregator, $date, $segments);
        } elseif ($from && $to) {
            $this->aggregateDateRange($aggregator, $from, $to, $segments);
        } else {
            $this->aggregateLastDays($aggregator, $days, $segments);
        }

        return self::SUCCESS;
    }

    /**
     * Aggregate a single date.
     *
     * @param  array<int, string>  $segments
     */
    private function aggregateSingleDate(MetricsAggregator $aggregator, string $date, array $segments): void
    {
        $carbonDate = Carbon::parse($date);

        $this->info("Aggregating metrics for {$date}...");

        // Aggregate without segment
        $aggregator->aggregateForDate($carbonDate);
        $this->line('  ✓ Global metrics');

        // Aggregate for each segment
        foreach ($segments as $segment) {
            $aggregator->aggregateForDate($carbonDate, $segment);
            $this->line("  ✓ Segment: {$segment}");
        }

        $this->info('Done!');
    }

    /**
     * Aggregate a date range.
     *
     * @param  array<int, string>  $segments
     */
    private function aggregateDateRange(MetricsAggregator $aggregator, string $from, string $to, array $segments): void
    {
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $totalDays = (int) $fromDate->diffInDays($toDate) + 1;

        $this->info("Aggregating metrics from {$from} to {$to} ({$totalDays} days)...");

        $bar = $this->output->createProgressBar($totalDays * (1 + count($segments)));
        $bar->start();

        $current = $fromDate->copy();

        while ($current->lte($toDate)) {
            // Aggregate without segment
            $aggregator->aggregateForDate($current);
            $bar->advance();

            // Aggregate for each segment
            foreach ($segments as $segment) {
                $aggregator->aggregateForDate($current, $segment);
                $bar->advance();
            }

            $current->addDay();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');
    }

    /**
     * Aggregate last N days.
     *
     * @param  array<int, string>  $segments
     */
    private function aggregateLastDays(MetricsAggregator $aggregator, int $days, array $segments): void
    {
        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        $this->aggregateDateRange(
            $aggregator,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
            $segments,
        );
    }
}
