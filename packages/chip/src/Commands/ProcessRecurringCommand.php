<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Services\RecurringService;
use Illuminate\Console\Command;
use Throwable;

final class ProcessRecurringCommand extends Command
{
    protected $signature = 'chip:process-recurring
                            {--dry-run : Show what would be processed without actually processing}';

    protected $description = 'Process due recurring payment schedules';

    public function handle(RecurringService $service): int
    {
        $due = $service->getDueSchedules();

        if ($due->isEmpty()) {
            $this->info('No recurring schedules are due for processing.');

            return self::SUCCESS;
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

            return self::SUCCESS;
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
        $this->info("Processing complete: {$succeeded} succeeded, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
