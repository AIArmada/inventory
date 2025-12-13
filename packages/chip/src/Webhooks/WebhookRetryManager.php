<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\WebhookResult;
use AIArmada\Chip\Models\Webhook;
use Throwable;

/**
 * Manages webhook retry logic with exponential backoff.
 */
class WebhookRetryManager
{
    /**
     * @var array<int, int> Backoff schedule in seconds: [attempt => delay]
     */
    protected array $backoffSchedule = [
        1 => 60,        // 1 minute
        2 => 300,       // 5 minutes
        3 => 900,       // 15 minutes
        4 => 3600,      // 1 hour
        5 => 14400,     // 4 hours
    ];

    public function __construct(
        protected WebhookEnricher $enricher,
        protected WebhookRouter $router,
    ) {}

    /**
     * Check if the webhook should be retried.
     */
    public function shouldRetry(Webhook $webhook): bool
    {
        if ($webhook->status !== 'failed') {
            return false;
        }

        return $webhook->retry_count < count($this->backoffSchedule);
    }

    /**
     * Get the next retry delay in seconds.
     */
    public function getNextRetryDelay(Webhook $webhook): int
    {
        $nextAttempt = $webhook->retry_count + 1;

        return $this->backoffSchedule[$nextAttempt] ?? $this->backoffSchedule[5];
    }

    /**
     * Retry processing a failed webhook.
     */
    public function retry(Webhook $webhook): WebhookResult
    {
        $webhook->increment('retry_count');
        $webhook->update(['last_retry_at' => now()]);

        try {
            $payload = $webhook->payload;
            $enriched = $this->enricher->enrich($webhook->event, $payload);
            $result = $this->router->route($webhook->event, $enriched);

            if ($result->isSuccess()) {
                $webhook->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
            } else {
                $webhook->update([
                    'last_error' => $result->message,
                ]);
            }

            return $result;

        } catch (Throwable $e) {
            $webhook->update([
                'last_error' => $e->getMessage(),
            ]);

            return WebhookResult::failed($e->getMessage());
        }
    }

    /**
     * Get all webhooks that should be retried.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Webhook>
     */
    public function getRetryableWebhooks(): \Illuminate\Database\Eloquent\Collection
    {
        return Webhook::query()
            ->where('status', 'failed')
            ->where('retry_count', '<', count($this->backoffSchedule))
            ->where(function ($query) {
                $query->whereNull('last_retry_at')
                    ->orWhereRaw('last_retry_at < DATE_SUB(NOW(), INTERVAL retry_count * 60 SECOND)');
            })
            ->get();
    }

    /**
     * Set a custom backoff schedule.
     *
     * @param  array<int, int>  $schedule
     */
    public function setBackoffSchedule(array $schedule): self
    {
        $this->backoffSchedule = $schedule;

        return $this;
    }
}
