<?php

declare(strict_types=1);

namespace AIArmada\Customers\Console\Commands;

use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\SegmentationService;
use Illuminate\Console\Command;

/**
 * Artisan command to rebuild automatic customer segments.
 *
 * Usage:
 *   php artisan customers:rebuild-segments          # Rebuild all segments
 *   php artisan customers:rebuild-segments --segment=uuid  # Rebuild specific segment
 */
class RebuildSegmentsCommand extends Command
{
    protected $signature = 'customers:rebuild-segments
                            {--segment= : Specific segment UUID to rebuild}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Rebuild automatic customer segment memberships';

    public function handle(SegmentationService $service): int
    {
        $segmentId = $this->option('segment');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Running in dry-run mode. No changes will be made.');
        }

        if ($segmentId) {
            return $this->rebuildSingleSegment($service, $segmentId, $dryRun);
        }

        return $this->rebuildAllSegments($service, $dryRun);
    }

    protected function rebuildSingleSegment(SegmentationService $service, string $segmentId, bool $dryRun): int
    {
        $segment = Segment::find($segmentId);

        if (! $segment) {
            $this->components->error("Segment with ID '{$segmentId}' not found.");

            return self::FAILURE;
        }

        if (! $segment->is_automatic) {
            $this->components->warn("Segment '{$segment->name}' is manual and cannot be rebuilt automatically.");

            return self::SUCCESS;
        }

        $this->components->info("Rebuilding segment: {$segment->name}");

        if ($dryRun) {
            $matchCount = $segment->getMatchingCustomers()->count();
            $currentCount = $segment->customers()->count();
            $this->components->twoColumnDetail($segment->name, "{$matchCount} matching (currently {$currentCount})");

            return self::SUCCESS;
        }

        $count = $service->rebuildSegment($segment);
        $this->components->twoColumnDetail($segment->name, "{$count} customers");
        $this->components->success('Segment rebuilt successfully.');

        return self::SUCCESS;
    }

    protected function rebuildAllSegments(SegmentationService $service, bool $dryRun): int
    {
        $segments = Segment::query()->active()->automatic()->get();

        if ($segments->isEmpty()) {
            $this->components->info('No automatic segments found.');

            return self::SUCCESS;
        }

        $this->components->info("Rebuilding {$segments->count()} automatic segments...");

        $results = [];

        foreach ($segments as $segment) {
            if ($dryRun) {
                $matchCount = $segment->getMatchingCustomers()->count();
                $currentCount = $segment->customers()->count();
                $results[$segment->name] = "{$matchCount} matching (currently {$currentCount})";
            } else {
                $count = $service->rebuildSegment($segment);
                $results[$segment->name] = "{$count} customers";
            }
        }

        $this->newLine();
        $this->components->bulletList(
            collect($results)->map(fn ($count, $name) => "{$name}: {$count}")->toArray()
        );
        $this->newLine();

        if (! $dryRun) {
            $this->components->success('All segments rebuilt successfully.');
        }

        return self::SUCCESS;
    }
}
