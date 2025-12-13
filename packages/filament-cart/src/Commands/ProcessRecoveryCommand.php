<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Commands;

use AIArmada\FilamentCart\Services\RecoveryScheduler;
use Illuminate\Console\Command;

class ProcessRecoveryCommand extends Command
{
    protected $signature = 'cart:process-recovery
                            {--limit=100 : Maximum number of attempts to process}
                            {--retry-failed : Also retry failed attempts}';

    protected $description = 'Process scheduled recovery attempts';

    public function handle(RecoveryScheduler $scheduler): int
    {
        $limit = (int) $this->option('limit');
        $retryFailed = $this->option('retry-failed');

        $this->info('Processing scheduled recovery attempts...');

        $result = $scheduler->processScheduledAttempts();

        $this->info("Processed: {$result['processed']} attempts");

        if ($result['failed'] > 0) {
            $this->warn("Failed: {$result['failed']} attempts");
        }

        if ($retryFailed) {
            $this->line('');
            $this->info('Retry of failed attempts is not yet implemented.');
        }

        return self::SUCCESS;
    }
}
