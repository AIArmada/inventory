<?php

declare(strict_types=1);

namespace AIArmada\Chip\Webhooks;

use AIArmada\Chip\Data\WebhookHealth;
use AIArmada\Chip\Models\Webhook;
use Illuminate\Support\Carbon;

/**
 * Monitors webhook health and provides statistics.
 */
class WebhookMonitor
{
    /**
     * Get webhook health metrics for the last 24 hours.
     */
    public function getHealth(?Carbon $since = null): WebhookHealth
    {
        $since ??= now()->subDay();

        $stats = Webhook::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                AVG(processing_time_ms) as avg_processing_time
            ')
            ->first();

        return WebhookHealth::fromStats(
            total: (int) ($stats->total ?? 0),
            processed: (int) ($stats->processed ?? 0),
            failed: (int) ($stats->failed ?? 0),
            pending: (int) ($stats->pending ?? 0),
            avgProcessingTimeMs: (float) ($stats->avg_processing_time ?? 0),
        );
    }

    /**
     * Get event distribution for the last 24 hours.
     *
     * @return array<string, int>
     */
    public function getEventDistribution(?Carbon $since = null): array
    {
        $since ??= now()->subDay();

        return Webhook::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event')
            ->toArray();
    }

    /**
     * Get failed webhooks count by error reason.
     *
     * @return array<string, int>
     */
    public function getFailureBreakdown(?Carbon $since = null): array
    {
        $since ??= now()->subDay();

        return Webhook::query()
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->selectRaw('COALESCE(last_error, "Unknown") as error, COUNT(*) as count')
            ->groupBy('error')
            ->pluck('count', 'error')
            ->toArray();
    }

    /**
     * Get hourly webhook volume for the last 24 hours.
     *
     * @return array<string, array{total: int, processed: int, failed: int}>
     */
    public function getHourlyVolume(?Carbon $since = null): array
    {
        $since ??= now()->subDay();

        return Webhook::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('
                DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = "processed" THEN 1 ELSE 0 END) as processed,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->keyBy('hour')
            ->map(fn ($row) => [
                'total' => (int) $row->total,
                'processed' => (int) $row->processed,
                'failed' => (int) $row->failed,
            ])
            ->toArray();
    }

    /**
     * Get pending webhooks that haven't been processed.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Webhook>
     */
    public function getPendingWebhooks(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return Webhook::query()
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently failed webhooks.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Webhook>
     */
    public function getRecentFailures(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return Webhook::query()
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
