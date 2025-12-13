<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Webhooks\WebhookRetryManager;
use Illuminate\Console\Command;
use Throwable;

final class RetryWebhooksCommand extends Command
{
    protected $signature = 'chip:retry-webhooks
                            {--limit=100 : Maximum number of webhooks to retry}
                            {--dry-run : Show what would be retried without actually processing}';

    protected $description = 'Retry failed webhooks with exponential backoff';

    public function handle(WebhookRetryManager $retryManager): int
    {
        $webhooks = $retryManager->getRetryableWebhooks()
            ->take((int) $this->option('limit'));

        if ($webhooks->isEmpty()) {
            $this->info('No webhooks are eligible for retry.');

            return self::SUCCESS;
        }

        $this->info("Found {$webhooks->count()} webhook(s) eligible for retry.");

        if ($this->option('dry-run')) {
            $this->warn('Dry run mode - no webhooks will be processed.');
            $this->newLine();

            $this->table(
                ['ID', 'Event', 'Retry Count', 'Last Error'],
                $webhooks->map(fn ($w) => [
                    $w->id,
                    $w->event,
                    $w->retry_count,
                    \Illuminate\Support\Str::limit($w->last_error ?? 'N/A', 50),
                ])->toArray()
            );

            return self::SUCCESS;
        }

        $this->newLine();

        $succeeded = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            $this->line("Retrying webhook {$webhook->id} ({$webhook->event})...");

            try {
                $result = $retryManager->retry($webhook);

                if ($result->isSuccess()) {
                    $this->info("  ✓ Retry succeeded");
                    $succeeded++;
                } else {
                    $this->warn("  ✗ Retry failed: {$result->message}");
                    $failed++;
                }
            } catch (Throwable $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failed++;
                report($e);
            }
        }

        $this->newLine();
        $this->info("Retry complete: {$succeeded} succeeded, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
