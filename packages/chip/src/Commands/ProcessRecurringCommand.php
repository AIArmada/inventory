<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\RecurringSchedule;
use AIArmada\Chip\Services\RecurringService;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class ProcessRecurringCommand extends Command
{
    protected $signature = 'chip:process-recurring
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Process due recurring payment schedules';

    public function handle(RecurringService $service): int
    {
        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owners = RecurringSchedule::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            if ($owners->isEmpty()) {
                $result = $this->processSchedules($service);

                $this->info("Processing complete: {$result['succeeded']} succeeded, {$result['failed']} failed.");

                return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
            }

            $totals = [
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ];

            foreach ($owners as $row) {
                $owner = $this->resolveOwnerFromRow($row);

                $result = OwnerContext::withOwner($owner, fn (): array => $this->processSchedules($service));

                $totals['processed'] += $result['processed'];
                $totals['succeeded'] += $result['succeeded'];
                $totals['failed'] += $result['failed'];
            }

            $this->info("Processing complete: {$totals['succeeded']} succeeded, {$totals['failed']} failed.");

            return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $result = $this->processSchedules($service);

        $this->info("Processing complete: {$result['succeeded']} succeeded, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    private function processSchedules(RecurringService $service): array
    {
        $due = $service->getDueSchedules();

        if ($due->isEmpty()) {
            $this->info('No recurring schedules are due for processing.');

            return [
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ];
        }

        $this->info("Found {$due->count()} schedule(s) due for processing.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode - no charges will be processed.');
            $this->newLine();

            $this->table(
                ['ID', 'Client', 'Amount', 'Currency', 'Next Charge'],
                $due->map(fn ($s) => [
                    $s->id,
                    $s->chip_client_id,
                    number_format($s->amount_minor / 100, 2),
                    $s->currency,
                    $s->next_charge_at?->toDateTimeString(),
                ])->toArray()
            );

            return [
                'processed' => $due->count(),
                'succeeded' => 0,
                'failed' => 0,
            ];
        }

        $this->newLine();

        $succeeded = 0;
        $failed = 0;

        foreach ($due as $schedule) {
            $this->line("Processing schedule {$schedule->id}...");

            try {
                $charge = $service->processCharge($schedule);

                if ($charge->isSuccess()) {
                    $this->info("  ✓ Charge succeeded: {$charge->getAmountFormatted()}");
                    $succeeded++;
                } else {
                    $this->warn("  ✗ Charge failed: {$charge->failure_reason}");
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failed++;
                report($e);
            }
        }

        $this->newLine();

        return [
            'processed' => $due->count(),
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
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
