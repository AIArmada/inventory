<?php

declare(strict_types=1);

namespace AIArmada\Chip\Commands;

use AIArmada\Chip\Models\Webhook;
use AIArmada\Chip\Webhooks\WebhookRetryManager;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class RetryWebhooksCommand extends Command
{
    protected $signature = 'chip:retry-webhooks
                            {--limit=100 : Maximum number of webhooks to retry}
                            {--dry-run : Show what would be retried without actually processing}';

    protected $description = 'Retry failed webhooks with exponential backoff';

    public function handle(WebhookRetryManager $retryManager): int
    {
        if ((bool) config('chip.owner.enabled', false) && OwnerContext::resolve() === null) {
            $owners = Webhook::query()
                ->withoutOwnerScope()
                ->select(['owner_type', 'owner_id'])
                ->distinct()
                ->get();

            if ($owners->isEmpty()) {
                $result = $this->processRetries($retryManager);

                $this->info("Retry complete: {$result['succeeded']} succeeded, {$result['failed']} failed.");

                return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
            }

            $totals = [
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ];

            foreach ($owners as $row) {
                $owner = $this->resolveOwnerFromRow($row);

                $result = OwnerContext::withOwner($owner, fn (): array => $this->processRetries($retryManager));

                $totals['processed'] += $result['processed'];
                $totals['succeeded'] += $result['succeeded'];
                $totals['failed'] += $result['failed'];
            }

            $this->info("Retry complete: {$totals['succeeded']} succeeded, {$totals['failed']} failed.");

            return $totals['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $result = $this->processRetries($retryManager);

        $this->info("Retry complete: {$result['succeeded']} succeeded, {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{processed: int, succeeded: int, failed: int}
     */
    private function processRetries(WebhookRetryManager $retryManager): array
    {
        $webhooks = $retryManager->getRetryableWebhooks()
            ->take((int) $this->option('limit'));

        if ($webhooks->isEmpty()) {
            $this->info('No webhooks are eligible for retry.');

            return [
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
            ];
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

            return [
                'processed' => $webhooks->count(),
                'succeeded' => 0,
                'failed' => 0,
            ];
        }

        $this->newLine();

        $succeeded = 0;
        $failed = 0;

        foreach ($webhooks as $webhook) {
            $this->line("Retrying webhook {$webhook->id} ({$webhook->event})...");

            try {
                $result = $retryManager->retry($webhook);

                if ($result->isSuccess()) {
                    $this->info('  ✓ Retry succeeded');
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

        return [
            'processed' => $webhooks->count(),
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
